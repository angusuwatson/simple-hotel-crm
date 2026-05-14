(function($) {
    'use strict';

    $(function() {
        var restUrl = simpleHotelCrm.restUrl;
        var dailyNotesUrl = simpleHotelCrm.dailyNotesUrl;
        var quickBookingUrl = simpleHotelCrm.quickBookingUrl;
        var roomDayNoteUrl = simpleHotelCrm.roomDayNoteUrl;
        var roomDayExtrasUrl = simpleHotelCrm.roomDayExtrasUrl;
        var nonce = simpleHotelCrm.nonce;
        var saveTimers = {};

        function getContainer() {
            return $('.simple-hotel-crm-container');
        }

        function request(options) {
            return $.ajax($.extend({}, options, {
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    if (typeof options.beforeSend === 'function') {
                        options.beforeSend(xhr);
                    }
                }
            }));
        }

        function setSavingState($el, state) {
            $el.toggleClass('is-saving', state === 'saving');
            $el.toggleClass('is-error', state === 'error');
        }

        function loadMonth(month, year, pushState) {
            var $container = getContainer();
            if (!$container.length) {
                return;
            }

            var savedScrollLeft = $('.simple-hotel-crm').scrollLeft() || 0;

            request({
                url: restUrl,
                method: 'GET',
                data: { month: month, year: year, context: simpleHotelCrm.context || 'frontend' },
                beforeSend: function() { $container.addClass('loading'); },
                success: function(response) {
                    if (response && response.html) {
                        $container.replaceWith(response.html);
                        $('.simple-hotel-crm').scrollLeft(savedScrollLeft);
                        if (pushState) {
                            var newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('month', month);
                            newUrl.searchParams.set('year', year);
                            window.history.pushState({ month: month, year: year }, '', newUrl.toString());
                        }
                    } else {
                        alert('Failed to load calendar data: empty response.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    alert('Error loading calendar (status: ' + status + '). See console for details.');
                },
                complete: function() { getContainer().removeClass('loading'); }
            });
        }

        function debounceSave(key, callback) {
            clearTimeout(saveTimers[key]);
            saveTimers[key] = setTimeout(callback, 350);
        }

        function saveDailyNote($input) {
            var date = $input.data('note-date');
            if (!date) return;
            setSavingState($input, 'saving');
            request({
                url: dailyNotesUrl,
                method: 'POST',
                data: { date: date, note: $input.val() },
                success: function() { setSavingState($input, 'done'); },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    setSavingState($input, 'error');
                }
            });
        }

        function getModal() {
            return $('.simple-hotel-crm-modal');
        }

        function getRoomNoteModal() {
            return $('.simple-hotel-crm-room-note-modal');
        }

        function closeModal() {
            var $modal = getModal();
            $modal.hide().attr('aria-hidden', 'true');
            $modal.find('.simple-hotel-crm-quick-booking-message').removeClass('error success').text('');
        }

        function closeRoomNoteModal() {
            var $modal = getRoomNoteModal();
            $modal.hide().attr('aria-hidden', 'true');
            $modal.find('.simple-hotel-crm-room-note-message').removeClass('error success').text('');
        }

        function getRoomExtrasModal() {
            return $('.simple-hotel-crm-room-extras-modal');
        }

        function closeRoomExtrasModal() {
            var $modal = getRoomExtrasModal();
            $modal.hide().attr('aria-hidden', 'true');
            $modal.find('.simple-hotel-crm-room-extras-message').removeClass('error success').text('');
        }

        function openQuickBooking(bookingId, reservedRoomId) {
            var $modal = getModal();
            var $form = $modal.find('.simple-hotel-crm-quick-booking-form');
            var $message = $modal.find('.simple-hotel-crm-quick-booking-message');
            $message.removeClass('error success').text('Loading...');
            $modal.show().attr('aria-hidden', 'false');
            request({
                url: quickBookingUrl,
                method: 'GET',
                data: { booking_id: bookingId, reserved_room_id: reservedRoomId || 0 },
                success: function(response) {
                    if (!response || !response.id) {
                        $message.addClass('error').text('Failed to load booking.');
                        return;
                    }
                    $form.find('[name="booking_id"]').val(response.id);
                    $form.find('[name="reserved_room_id"]').val(reservedRoomId || '');
                    $form.find('[name="guest_name"]').val(response.guest_name || '');
                    $form.find('[name="phone"]').val(response.phone || '');
                    $form.find('[name="email"]').val(response.email || '');
                    $form.find('[name="contacted_date"]').val(response.contacted_date || '');
                    $form.find('[name="internal_notes"]').val(response.internal_notes || '');
                    $form.find('[name="status_code"]').val(response.status_code || '');
                    $modal.find('.simple-hotel-crm-open-full-booking').attr('href', response.detail_url || '#');
                    $modal.find('.simple-hotel-crm-open-guest').attr('href', response.guest_url || '#');
                    $message.text('');
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    $message.addClass('error').text('Failed to load booking.');
                }
            });
        }

        $(document).on('click', '.quick-booking-trigger', function(e) {
            e.preventDefault();
            openQuickBooking($(this).data('booking-id'), $(this).data('reserved-room-id'));
        });

        $(document).on('click', '.simple-hotel-crm-modal-backdrop, .simple-hotel-crm-modal-close', function() {
            closeModal();
        });

        $(document).on('click', '.simple-hotel-crm-room-note-close', function() {
            closeRoomNoteModal();
        });

        $(document).on('click', '.simple-hotel-crm-room-extras-close', function() {
            closeRoomExtrasModal();
        });

        $(document).on('click', '.simple-hotel-crm-copy-button', function() {
            var $button = $(this);
            var target = $button.data('copy-target');
            var $input = $button.closest('.simple-hotel-crm-copy-field').find('[name="' + target + '"]');
            var value = ($input.val() || '').toString();
            if (!value) {
                return;
            }
            function copied() {
                var oldText = $button.text();
                $button.text('Copied');
                setTimeout(function() { $button.text(oldText); }, 1000);
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(copied).catch(function() {
                    $input.trigger('focus').trigger('select');
                    try {
                        if (document.execCommand('copy')) copied();
                    } catch (e) {}
                });
            } else {
                $input.trigger('focus').trigger('select');
                try {
                    if (document.execCommand('copy')) copied();
                } catch (e) {}
            }
        });

        $(document).on('submit', '.simple-hotel-crm-quick-booking-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $message = $form.find('.simple-hotel-crm-quick-booking-message');
            $message.removeClass('error success').text('Saving...');
            request({
                url: quickBookingUrl,
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response && response.success) {
                        $message.addClass('success').text('Saved.');
                        var params = new URLSearchParams(window.location.search);
                        loadMonth(params.get('month') || new Date().getMonth() + 1, params.get('year') || new Date().getFullYear(), false);
                        setTimeout(closeModal, 500);
                    } else {
                        $message.addClass('error').text('Save failed.');
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    $message.addClass('error').text((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed.');
                }
            });
        });

        $(document).on('click', '.simple-hotel-crm-container .calendar-nav .button, .simple-hotel-crm-container .calendar-month-tab', function(e) {
            var href = $(this).attr('href');
            if (!href) return;
            e.preventDefault();
            var url = new URL(href, window.location.origin);
            var month = url.searchParams.get('month');
            var year = url.searchParams.get('year');
            if (month && year) loadMonth(month, year, true);
        });

        $(document).on('input', '.simple-hotel-crm-container .calendar-note-input', function() {
            var $input = $(this);
            debounceSave('note:' + $input.data('note-date'), function() { saveDailyNote($input); });
        });

        $(document).on('click', '[data-room-day-note-cell="1"]', function() {
            var $cell = $(this);
            var $modal = getRoomNoteModal();
            var $form = $modal.find('.simple-hotel-crm-room-note-form');
            $form.find('[name="booking_id"]').val($cell.data('booking-id') || '');
            $form.find('[name="booking_room_id"]').val($cell.data('booking-room-id') || '');
            $form.find('[name="stay_date"]').val($cell.data('stay-date') || '');
            $form.find('[name="note"]').val(($cell.data('note-text') || '').toString());
            $modal.find('.simple-hotel-crm-room-note-message').removeClass('error success').text('');
            $modal.show().attr('aria-hidden', 'false');
            setTimeout(function() {
                var field = $form.find('[name="note"]').get(0);
                if (field) {
                    field.focus();
                    field.setSelectionRange(field.value.length, field.value.length);
                }
            }, 10);
        });

        $(document).on('click', '.simple-hotel-crm-room-note-modal .simple-hotel-crm-modal-backdrop', function() {
            closeRoomNoteModal();
        });

        $(document).on('submit', '.simple-hotel-crm-room-note-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $message = $form.find('.simple-hotel-crm-room-note-message');
            $message.removeClass('error success').text('Saving...');
            request({
                url: roomDayNoteUrl,
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response && response.success) {
                        $message.addClass('success').text('Saved.');
                        var params = new URLSearchParams(window.location.search);
                        loadMonth(params.get('month') || new Date().getMonth() + 1, params.get('year') || new Date().getFullYear(), false);
                        setTimeout(closeRoomNoteModal, 400);
                    } else {
                        $message.addClass('error').text('Save failed.');
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    $message.addClass('error').text((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed.');
                }
            });
        });

        $(document).on('click', '[data-room-day-extras-cell="1"]', function() {
            var $cell = $(this);
            var $modal = getRoomExtrasModal();
            var $form = $modal.find('.simple-hotel-crm-room-extras-form');
            $form.find('[name="booking_id"]').val($cell.data('booking-id') || '');
            $form.find('[name="booking_room_id"]').val($cell.data('booking-room-id') || '');
            $form.find('[name="stay_date"]').val($cell.data('stay-date') || '');
            $form.find('[name="formula"]').val(($cell.data('extras-formula') || '').toString());
            $form.find('[name="amount"]').val(($cell.data('extras-amount') || '').toString());
            $modal.find('.simple-hotel-crm-room-extras-message').removeClass('error success').text('');
            $modal.show().attr('aria-hidden', 'false');
            setTimeout(function() {
                var field = $form.find('[name="formula"]').get(0);
                if (field) {
                    field.focus();
                    field.setSelectionRange(field.value.length, field.value.length);
                }
            }, 10);
        });

        $(document).on('click', '.simple-hotel-crm-room-extras-modal .simple-hotel-crm-modal-backdrop', function() {
            closeRoomExtrasModal();
        });

        $(document).on('submit', '.simple-hotel-crm-room-extras-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $message = $form.find('.simple-hotel-crm-room-extras-message');
            $message.removeClass('error success').text('Saving...');
            request({
                url: roomDayExtrasUrl,
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response && response.success) {
                        $message.addClass('success').text('Saved.');
                        var params = new URLSearchParams(window.location.search);
                        loadMonth(params.get('month') || new Date().getMonth() + 1, params.get('year') || new Date().getFullYear(), false);
                        setTimeout(closeRoomExtrasModal, 400);
                    } else {
                        $message.addClass('error').text('Save failed.');
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    $message.addClass('error').text((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed.');
                }
            });
        });

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.month && e.state.year) {
                loadMonth(e.state.month, e.state.year, false);
            } else {
                var params = new URLSearchParams(window.location.search);
                var month = params.get('month');
                var year = params.get('year');
                if (month && year) loadMonth(month, year, false);
            }
        });

        function saveScrollPosition() {
            var $scroller = $('.simple-hotel-crm');
            if ($scroller.length) {
                sessionStorage.setItem('shc_calendar_scroll', $scroller.scrollLeft());
            }
        }

        function restoreScrollPosition() {
            var saved = sessionStorage.getItem('shc_calendar_scroll');
            if (saved !== null) {
                var val = parseInt(saved, 10);
                if (val > 0) {
                    $('.simple-hotel-crm').scrollLeft(val);
                }
                sessionStorage.removeItem('shc_calendar_scroll');
            }
        }

        $(window).on('beforeunload', saveScrollPosition);

        function scrollToTodayIfVisible() {
            var $container = getContainer();
            if (!$container.length || $container.data('scroll-to-today') !== 1 && $container.data('scroll-to-today') !== '1') return;
            var today = new Date();
            var selector = '.simple-hotel-crm-container [data-date="' + today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0') + '"]';
            var $cell = $(selector).first();
            if (!$cell.length) return;
            var $scroller = $('.simple-hotel-crm');
            if (!$scroller.length) return;
            var left = Math.max(0, $cell.position().left - 300);
            $scroller.animate({ scrollLeft: left }, 250);
        }

        (function() {
            var params = new URLSearchParams(window.location.search);
            var month = params.get('month');
            var year = params.get('year');
            if (month && year) {
                window.history.replaceState({ month: month, year: year }, '', window.location.href);
            }
            restoreScrollPosition();
        })();
    });
})(jQuery);
