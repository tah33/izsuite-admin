import './bootstrap';

// jQuery and Chart.js are loaded via CDN in the layout
// Custom app-wide JS below

(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    function isAdminPanelPath() {
        return window.location.pathname === '/admin' || window.location.pathname.startsWith('/admin/');
    }

    function isAdminActionUrl(url) {
        try {
            var parsed = new URL(url, window.location.origin);
            return parsed.pathname === '/admin' || parsed.pathname.startsWith('/admin/');
        } catch (error) {
            return false;
        }
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function ensureToastContainer() {
        var $container = $('#admin-toast-container');
        if (! $container.length) {
            $container = $('<div id="admin-toast-container"></div>').appendTo(document.body);
        }
        return $container;
    }

    function adminToast(type, message, duration) {
        duration = duration || 4500;

        var $container = ensureToastContainer();
        var $toast = $('<div class="admin-toast"></div>')
            .addClass('admin-toast--' + type)
            .text(message || '');

        $container.append($toast);

        requestAnimationFrame(function () {
            $toast.addClass('is-visible');
        });

        setTimeout(function () {
            $toast.removeClass('is-visible');
            setTimeout(function () {
                $toast.remove();
            }, 250);
        }, duration);
    }

    window.AdminToast = {
        success: function (message) { adminToast('success', message); },
        error: function (message) { adminToast('error', message); },
        info: function (message) { adminToast('info', message); },
    };

    var SPINNER_SVG = '<svg class="admin-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-3px;margin-right:6px"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

    function setLoading($button, formMethod) {
        if (! $button || ! $button.length || $button.data('admin-loading') === true) {
            return;
        }

        var label = $button.data('loading-label');
        if (! label) {
            label = formMethod === 'DELETE' ? 'Deleting...' : 'Saving...';
        }

        $button.data('admin-loading', true);
        if ($button.is('input[type="submit"]')) {
            $button.data('admin-original-value', $button.val());
            $button.prop('disabled', true).addClass('is-loading').val(label);
            return;
        }

        $button.data('admin-original-html', $button.html());
        $button.prop('disabled', true).addClass('is-loading').html(SPINNER_SVG + label);
    }

    function clearLoading($button) {
        if (! $button || ! $button.length || ! $button.data('admin-loading')) {
            return;
        }

        $button.prop('disabled', false).removeClass('is-loading');

        if ($button.is('input[type="submit"]')) {
            $button.val($button.data('admin-original-value'));
            $button.removeData('admin-original-value');
        } else {
            $button.html($button.data('admin-original-html'));
            $button.removeData('admin-original-html');
        }

        $button.removeData('admin-loading');
    }

    function clearFieldErrors($form) {
        $form.find('.field-error').remove();
        $form.find('.form-input, .form-select, .form-textarea, .form-control')
            .removeClass('has-error form-input-error is-invalid')
            .removeAttr('aria-invalid');
        $form.find('.select2-container').removeClass('has-error');
    }

    // A <select> that Select2 has taken over is hidden; what the user sees -
    // and what an error belongs under - is the control drawn after it.
    function visibleField($input) {
        if ($input.hasClass('select2-hidden-accessible')) {
            var $control = $input.next('.select2-container');
            if ($control.length) {
                return $control;
            }
        }

        return $input;
    }

    function dotNameToBracket(name) {
        if (name.indexOf('.') === -1) {
            return name;
        }

        var parts = name.split('.');
        var root = parts.shift();

        return root + parts.map(function (part) {
            return '[' + part + ']';
        }).join('');
    }

    function inputVariants(name) {
        var variants = [name, dotNameToBracket(name)];
        if (name.endsWith('.*')) {
            var withoutWildcard = name.slice(0, -2);
            variants.push(withoutWildcard);
            variants.push(dotNameToBracket(withoutWildcard) + '[]');
        }
        return variants;
    }

    function findInputByName($form, name) {
        var variants = inputVariants(name);
        var $inputs = $form.find(':input');
        var $match = $();

        variants.some(function (variant) {
            $match = $inputs.filter(function () {
                return this.name === variant;
            }).first();
            return $match.length > 0;
        });

        return $match;
    }

    function revealErrorField($input) {
        if (! $input.length) {
            return;
        }

        var $hiddenTab = $input.closest('.cms-panel.hidden[data-tab]');
        if ($hiddenTab.length) {
            var tabName = $hiddenTab.attr('data-tab');
            var tabButton = document.querySelector('.cms-tab[data-tab="' + tabName + '"]');
            if (tabButton) {
                tabButton.click();
            }
        }

        var field = visibleField($input)[0];
        if (field && typeof field.scrollIntoView === 'function') {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function renderFieldErrors($form, errors) {
        clearFieldErrors($form);

        var firstErroredInput = null;
        Object.keys(errors || {}).forEach(function (field) {
            var messages = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
            var $input = findInputByName($form, field);
            var message = messages.length ? String(messages[0]) : 'This field is invalid.';

            if (! $input.length) {
                return;
            }

            $input.addClass('has-error form-input-error is-invalid').attr('aria-invalid', 'true');

            var $field = visibleField($input);
            $field.addClass('has-error');

            var $error = $('<p class="field-error form-error"></p>').text(message);
            $field.after($error);

            if (! firstErroredInput) {
                firstErroredInput = $input;
            }
        });

        if (firstErroredInput) {
            revealErrorField(firstErroredInput);
        }
    }

    function urlsMatch(urlA, urlB) {
        try {
            var a = new URL(urlA, window.location.origin);
            var b = new URL(urlB, window.location.origin);
            return a.href === b.href;
        } catch (error) {
            return urlA === urlB;
        }
    }

    function handleAjaxFormSubmissions() {
        if (! isAdminPanelPath()) {
            return;
        }

        $(document).on('submit', 'form:not([data-no-ajax])', function (event) {
            if (event.isDefaultPrevented()) {
                return;
            }

            var $form = $(this);
            var method = String($form.attr('method') || 'POST').toUpperCase();
            var actionUrl = $form.attr('action') || window.location.href;

            if (! isAdminActionUrl(actionUrl)) {
                return;
            }

            if (method === 'GET') {
                return;
            }

            event.preventDefault();

            var activeElement = document.activeElement;
            var $submitButton = $(activeElement).is('button, input[type="submit"]')
                ? $(activeElement)
                : $();

            if (! $submitButton.length || $submitButton.closest('form')[0] !== this) {
                $submitButton = $form.find('button[type="submit"], input[type="submit"]').first();
            }

            clearFieldErrors($form);
            setLoading($submitButton, method);

            var formData = new FormData(this);

            $.ajax({
                url: actionUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            })
                .done(function (response, _textStatus, xhr) {
                    var responseData = response && typeof response === 'object' ? response : {};
                    var responseUrl = xhr && xhr.responseURL ? xhr.responseURL : '';
                    var redirectUrl = responseData.redirect || '';

                    if (! redirectUrl && responseUrl && ! urlsMatch(responseUrl, window.location.href)) {
                        redirectUrl = responseUrl;
                    }

                    var successMessage = responseData.message || 'Saved successfully.';
                    window.AdminToast.success(successMessage);

                    if ($form.attr('data-no-reload') !== undefined) {
                        clearLoading($submitButton);
                        $form.trigger('admin:saved', [responseData]);
                        return;
                    }

                    setTimeout(function () {
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                        } else {
                            window.location.reload();
                        }
                    }, 300);
                })
                .fail(function (xhr) {
                    var status = xhr.status;
                    var body = xhr.responseJSON || {};

                    if (status === 422 && body.errors) {
                        renderFieldErrors($form, body.errors);
                        window.AdminToast.error(body.message || 'Please fix the highlighted fields.');
                    } else if (status === 419) {
                        window.AdminToast.error('Your session expired. Reloading...');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                        return;
                    } else if (status === 401 || status === 403) {
                        window.AdminToast.error(body.message || 'You are not authorized to perform this action.');
                    } else {
                        window.AdminToast.error(body.message || 'Something went wrong. Please try again.');
                        if (window.console) {
                            console.error('[admin] AJAX form submit failed:', xhr);
                        }
                    }

                    clearLoading($submitButton);
                });
        });

        $(window).on('pageshow', function (event) {
            if (event.originalEvent && event.originalEvent.persisted) {
                $('button.is-loading, input.is-loading').each(function () {
                    clearLoading($(this));
                });
            }
        });
    }

    // ── Dropdowns: every <select> is a Select2 ───────────────────────────────
    //
    // The plugin, version and look the wwp admin uses (the theme is in
    // app.css). A select can opt out with data-no-select2.
    //
    // - The open list renders on <body>, so a card with overflow:hidden or a
    //   modal can never clip it.
    // - A search box shows once a list is long enough to need one.
    // - An empty option ("All plans", "Unassigned") stays an ordinary choice. Set
    //   as a placeholder, Select2 would drop it from the list and leave no way
    //   back to it - so a placeholder is only used when the <select> asks for
    //   one with data-placeholder.
    // - class="select-sm" draws the compact control.
    //
    // Select2 reports a pick with a jQuery 'change', not a native DOM event: a
    // page script listens with $(select).on('change', …) - a listener added
    // with addEventListener('change') never hears it. Inline onchange="…"
    // attributes do run. Setting a value from script needs
    // $(select).val(x).trigger('change') to repaint the control.
    var SELECT2_SEARCH_FROM = 8;

    function initSelect2(root) {
        if (! $.fn.select2) {
            return;
        }

        var noResults = $('meta[name="select2-no-results"]').attr('content') || 'No results found';

        $(root || document).find('select').not('[data-no-select2], .select2-hidden-accessible').each(function () {
            var $select = $(this);
            var options = {
                width: '100%',
                dropdownParent: $(document.body),
                minimumResultsForSearch: SELECT2_SEARCH_FROM,
                selectionCssClass: $select.hasClass('select-sm') ? 's2-sm' : '',
                language: {
                    noResults: function () {
                        return noResults;
                    },
                },
            };

            if ($select.attr('data-placeholder')) {
                options.placeholder = $select.attr('data-placeholder');
            }

            $select.select2(options);
        });
    }

    // For a page that adds selects after load.
    window.AdminSelect2 = { init: initSelect2 };

    $(document).ready(function () {
        handleAjaxFormSubmissions();
        initSelect2(document);

        // Sidebar toggle for mobile
        $('#sidebar-toggle').on('click', function () {
            $('#sidebar').toggleClass('open');
            $('#sidebar-overlay').toggleClass('show');
        });

        $('#sidebar-overlay').on('click', function () {
            $('#sidebar').removeClass('open');
            $(this).removeClass('show');
        });

        // Topbar Dropdowns
        $('#user-dropdown-toggle').on('click', function (e) {
            e.stopPropagation();
            $('#user-dropdown-menu').toggleClass('hidden');
            $('#lang-dropdown-menu, #curr-dropdown-menu').addClass('hidden');
        });

        $('#lang-dropdown-toggle').on('click', function (e) {
            e.stopPropagation();
            $('#lang-dropdown-menu').toggleClass('hidden');
            $('#user-dropdown-menu, #curr-dropdown-menu, #notification-dropdown-menu').addClass('hidden');
        });

        $('#curr-dropdown-toggle').on('click', function (e) {
            e.stopPropagation();
            $('#curr-dropdown-menu').toggleClass('hidden');
            $('#user-dropdown-menu, #lang-dropdown-menu, #notification-dropdown-menu').addClass('hidden');
        });

        $('#notification-dropdown-toggle').on('click', function (e) {
            e.stopPropagation();
            $('#notification-dropdown-menu').toggleClass('hidden');
            $('#user-dropdown-menu, #lang-dropdown-menu, #curr-dropdown-menu').addClass('hidden');
        });

        $(document).on('click', function () {
            $('#user-dropdown-menu, #lang-dropdown-menu, #curr-dropdown-menu, #notification-dropdown-menu').addClass('hidden');
        });

        // Modal open/close
        $(document).on('click', '[data-modal-open]', function () {
            var target = $(this).data('modal-open');
            $('#' + target).addClass('show');
        });

        $(document).on('click', '[data-modal-close]', function () {
            $(this).closest('.modal-overlay').removeClass('show');
        });

        $(document).on('click', '.modal-overlay', function (e) {
            if (e.target === this) {
                $(this).removeClass('show');
            }
        });

        // Persist sidebar scroll position
        var sidebarNav = document.getElementById('sidebar-nav');
        if (sidebarNav) {
            var savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos) {
                sidebarNav.scrollTop = savedScrollPos;
            }

            $(sidebarNav).on('scroll', function () {
                sessionStorage.setItem('sidebarScrollPos', this.scrollTop);
            });
        }

        // Show/hide toggle for <x-password-input>. Delegated so it works for
        // every password field on every page without per-page wiring — swaps
        // the input's type and the button's icon between eye/eye-off.
        $(document).on('click', '[data-toggle-password]', function () {
            var input = document.getElementById($(this).attr('data-toggle-password'));
            if (! input) {
                return;
            }

            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            $(this).attr('aria-label', showing ? 'Show password' : 'Hide password')
                .find('i, svg').first().replaceWith('<i data-lucide="' + (showing ? 'eye' : 'eye-off') + '" class="w-4 h-4"></i>');

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        });
    });
})(window.jQuery);
