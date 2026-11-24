/**
 * Gotenberg's stand-in on a PC without Docker (Laragon, say). The API's
 * GotenbergClient posts a document's HTML here as it does to Gotenberg, and
 * gets its PDF back - printed by the Chrome (or Edge) already installed, with
 * the headless Chromium print Gotenberg itself uses, so a PDF comes out as it
 * will in production.
 *
 * Only what the app asks of Gotenberg: POST /forms/chromium/convert/html
 * (index.html and footer.html, the paper, margins, printBackground and
 * singlePage) and GET /health. As in production, the page runs no JavaScript
 * and reaches no network: everything it shows travels inside its HTML.
 *
 *     npm run pdf        and in .env: GOTENBERG_URL=http://127.0.0.1:3100
 *
 * PDF_PORT sets another port, CHROME_PATH another browser. Plain Node (18 or
 * newer), nothing from npm. Each PDF gets a Chrome of its own, closed after.
 */
import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises'
import { createServer } from 'node:http'
import { tmpdir } from 'node:os'
import { basename, join } from 'node:path'
import { pathToFileURL } from 'node:url'

const PORT = Number(process.env.PDF_PORT || 3100)
const TIMEOUT = 55_000 // inside GotenbergClient's 60 s
const MAX_FORM = 50 * 1024 * 1024
const BLANK = '<html><head></head><body></body></html>' // Gotenberg's own empty header and footer

class HttpError extends Error {
  constructor(status, message) {
    super(message)
    this.status = status
  }
}

function findChrome() {
  if (process.env.CHROME_PATH) return existsSync(process.env.CHROME_PATH) ? process.env.CHROME_PATH : null

  const programs = [process.env.PROGRAMFILES, process.env['PROGRAMFILES(X86)'], process.env.LOCALAPPDATA].filter(Boolean)
  const candidates = {
    win32: ['Google/Chrome/Application/chrome.exe', 'Microsoft/Edge/Application/msedge.exe'].flatMap(exe => programs.map(dir => join(dir, exe))),
    darwin: ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'],
    linux: ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser'],
  }[process.platform] ?? []

  return candidates.find(path => existsSync(path)) ?? null
}

const CHROME = findChrome()

/** One headless Chrome, spoken to over the DevTools protocol on its own pipes - no port is opened. */
class Browser {
  #next = 0
  #calls = new Map()
  #events = []
  #waiters = new Set()
  #buffer = ''
  #stderr = ''

  constructor(profile) {
    this.child = spawn(CHROME, [
      '--headless', '--remote-debugging-pipe', `--user-data-dir=${profile}`,
      '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync',
      '--disable-background-networking', '--disable-component-update', '--disable-default-apps',
      '--hide-scrollbars', '--mute-audio',
      // No way out, as in production: every request goes to a proxy that is not there.
      '--proxy-server=http://127.0.0.1:9', '--proxy-bypass-list=<-loopback>',
    ], { stdio: ['ignore', 'ignore', 'pipe', 'pipe', 'pipe'], windowsHide: true })

    this.running = true
    this.child.stderr.setEncoding('utf8').on('data', text => (this.#stderr = (this.#stderr + text).slice(-2000)))
    this.child.stdio[3].on('error', () => {}) // Chrome gone: its exit says so below
    this.child.stdio[4].setEncoding('utf8').on('data', text => this.#read(text))

    this.exited = new Promise(resolve => this.child.once('exit', resolve).once('error', resolve)).then(reason => {
      this.running = false
      const last = this.#stderr.trim().split('\n').pop()
      this.#fail(new Error(reason instanceof Error ? `Chrome did not start: ${reason.message}` : `Chrome stopped. ${last ?? ''}`.trim()))
    })
  }

  send(method, params = {}, sessionId = undefined) {
    const id = ++this.#next

    return new Promise((resolve, reject) => {
      if (!this.running) return reject(new Error('Chrome has stopped.'))

      this.#calls.set(id, { resolve, reject })
      this.child.stdio[3].write(`${JSON.stringify({ id, method, params, sessionId })}\0`)
    })
  }

  /** The first event that matches - one already in, or the next to come. */
  event(matches) {
    const seen = this.#events.find(matches)

    return new Promise((resolve, reject) => {
      if (seen) return resolve(seen)
      if (!this.running) return reject(new Error('Chrome has stopped.'))

      this.#waiters.add({ matches, resolve, reject })
    })
  }

  async close() {
    if (!this.running) return

    this.send('Browser.close').catch(() => {})
    const timer = setTimeout(() => this.child.kill(), 5000)
    await this.exited
    clearTimeout(timer)
  }

  #read(text) {
    const messages = (this.#buffer + text).split('\0')
    this.#buffer = messages.pop()

    for (const raw of messages) {
      let message

      try {
        message = JSON.parse(raw)
      } catch {
        continue // never Chrome's way, but a stray line must not stop the server
      }

      const call = this.#calls.get(message.id)

      if (call) {
        this.#calls.delete(message.id)
        message.error ? call.reject(new Error(`${message.error.message} ${message.error.data ?? ''}`.trim())) : call.resolve(message.result)
        continue
      }

      this.#events.push(message)

      for (const waiter of this.#waiters) {
        if (waiter.matches(message)) {
          this.#waiters.delete(waiter)
          waiter.resolve(message)
        }
      }
    }
  }

  #fail(error) {
    for (const pending of [...this.#calls.values(), ...this.#waiters]) pending.reject(error)

    this.#calls.clear()
    this.#waiters.clear()
  }
}

/** The form Guzzle sends: its files (name and bytes) and its fields. Parsed here, as Node 18 cannot. */
function parseForm(body, type = '') {
  const boundary = /boundary=(?:"([^"]+)"|([^;\s]+))/i.exec(type)
  const first = boundary && body.indexOf(`--${boundary[1] ?? boundary[2]}`)

  if (!boundary || first === -1) throw new HttpError(400, 'Send the form as multipart/form-data.')

  // Each part follows "--boundary" and a line break: its headers, a blank line, its content.
  const delimiter = Buffer.from(`\r\n--${boundary[1] ?? boundary[2]}`)
  const files = []
  const fields = new Map()
  let start = first + delimiter.length - 2

  while (body.subarray(start, start + 2).toString() !== '--') {
    const end = body.indexOf(delimiter, start)
    const part = body.subarray(start + 2, end)
    const split = part.indexOf('\r\n\r\n')

    if (end === -1 || split === -1) throw new HttpError(400, 'The form ends half-way.')

    const head = part.subarray(0, split).toString()
    const name = /;\s*name="([^"]*)"/i.exec(head)?.[1]
    const filename = /;\s*filename="([^"]*)"/i.exec(head)?.[1]
    const content = part.subarray(split + 4)

    if (filename !== undefined) files.push({ name: basename(filename), content })
    else if (name !== undefined) fields.set(name, content.toString())

    start = end + delimiter.length
  }

  return { files, fields }
}

async function readForm(request) {
  const chunks = []
  let size = 0

  for await (const chunk of request) {
    if ((size += chunk.length) > MAX_FORM) throw new HttpError(413, 'The form is too large.')

    chunks.push(chunk)
  }

  return parseForm(Buffer.concat(chunks), request.headers['content-type'])
}

function printOptions({ files, fields }) {
  const inches = (field, fallback) => {
    const value = Number.parseFloat(fields.get(field))

    return Number.isFinite(value) ? value : fallback
  }
  const template = name => files.find(file => file.name === name)?.content.toString()
  const header = template('header.html')
  const footer = template('footer.html')

  return {
    printBackground: fields.get('printBackground') === 'true',
    paperWidth: inches('paperWidth', 8.5),
    paperHeight: inches('paperHeight', 11),
    marginTop: inches('marginTop', 0.39),
    marginBottom: inches('marginBottom', 0.39),
    marginLeft: inches('marginLeft', 0.39),
    marginRight: inches('marginRight', 0.39),
    displayHeaderFooter: Boolean(header || footer),
    headerTemplate: header ?? BLANK,
    footerTemplate: footer ?? BLANK,
  }
}

/** A receipt's roll, cut where it ends: one page as long as what is on it, laid out at the paper's width. */
async function onePage(page, options) {
  const width = options.paperWidth - options.marginLeft - options.marginRight

  await page('Emulation.setEmulatedMedia', { media: 'print' })
  await page('Emulation.setDeviceMetricsOverride', { width: Math.round(width * 96), height: 100, deviceScaleFactor: 1, mobile: false })
  const { cssContentSize } = await page('Page.getLayoutMetrics')
  await page('Emulation.clearDeviceMetricsOverride')

  return { paperHeight: (Math.ceil(cssContentSize.height) + 2) / 96 + options.marginTop + options.marginBottom, pageRanges: '1' }
}

async function makePdf(form) {
  const folder = await mkdtemp(join(tmpdir(), 'izsuite-pdf-'))
  const pageFolder = join(folder, 'page')
  let late = false
  let browser

  try {
    await mkdir(pageFolder)

    for (const file of form.files.filter(file => file.name)) await writeFile(join(pageFolder, file.name), file.content)

    browser = new Browser(join(folder, 'chrome'))
    const timer = setTimeout(() => ((late = true), browser.child.kill()), TIMEOUT)

    try {
      const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' })
      const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true })
      const page = (method, params) => browser.send(method, params, sessionId)

      await page('Page.enable')
      await page('Page.setLifecycleEventsEnabled', { enabled: true })
      await page('Emulation.setScriptExecutionDisabled', { value: true })

      const { loaderId, errorText } = await page('Page.navigate', { url: pathToFileURL(join(pageFolder, 'index.html')).href })

      if (errorText) throw new Error(`index.html did not open: ${errorText}`)

      // Loaded, and nothing more on its way for half a second: its images and fonts are in.
      await browser.event(e => e.sessionId === sessionId && e.params?.loaderId === loaderId && e.params?.name === 'networkIdle')

      const options = printOptions(form)

      if (form.fields.get('singlePage') === 'true') Object.assign(options, await onePage(page, options))

      const { data } = await page('Page.printToPDF', options)

      return Buffer.from(data, 'base64')
    } finally {
      clearTimeout(timer)
    }
  } catch (error) {
    throw late ? new Error(`Chrome took longer than ${TIMEOUT / 1000} s.`) : error
  } finally {
    await browser?.close()
    rm(folder, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 }).catch(() => {})
  }
}

const time = () => new Date().toTimeString().slice(0, 8)

function answer(response, status, type, body) {
  response.writeHead(status, { 'Content-Type': type, 'Content-Length': Buffer.byteLength(body) })
  response.end(body)
}

const server = createServer(async (request, response) => {
  const path = new URL(request.url, 'http://localhost').pathname

  try {
    if (request.method === 'GET' && path === '/health') {
      return answer(response, 200, 'application/json', JSON.stringify({ status: 'up', details: { chromium: { status: 'up' } } }))
    }

    if (request.method !== 'POST' || path !== '/forms/chromium/convert/html') throw new HttpError(404, 'Not found.')

    const form = await readForm(request)

    if (!form.files.some(file => file.name === 'index.html')) throw new HttpError(400, 'index.html is required.')

    const started = Date.now()
    const pdf = await makePdf(form)

    console.log(`${time()}  PDF made: ${Math.ceil(pdf.length / 1024)} KB in ${((Date.now() - started) / 1000).toFixed(1)} s`)
    answer(response, 200, 'application/pdf', pdf)
  } catch (error) {
    if (!error.status) console.error(`${time()}  No PDF: ${error.message}`)

    answer(response, error.status ?? 503, 'text/plain; charset=utf-8', error.status ? error.message : `The PDF could not be made: ${error.message}`)
  }
})

if (!CHROME) {
  console.error('No Chrome or Edge was found. Set CHROME_PATH to the browser\'s program file.')
  process.exit(1)
}

server.on('error', error => {
  console.error(error.code === 'EADDRINUSE' ? `Port ${PORT} is taken - is the PDF maker running already? PDF_PORT sets another.` : error.message)
  process.exit(1)
})

// Only this PC: the page it prints is the app's to send.
server.listen(PORT, '127.0.0.1', () => {
  console.log(`izSuite PDF maker on http://127.0.0.1:${PORT}, printing with ${CHROME}`)
  console.log(`The API finds it with GOTENBERG_URL=http://127.0.0.1:${PORT} in .env. Ctrl+C stops it.`)
})
