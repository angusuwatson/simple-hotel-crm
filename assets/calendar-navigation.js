(function($) {
    'use strict';

    $(function() {
        var restUrl = simpleHotelCrm.restUrl;
        var dailyNotesUrl = simpleHotelCrm.dailyNotesUrl;
        var bookingUrl = simpleHotelCrm.bookingUrl;
        var quickBookingUrl = simpleHotelCrm.quickBookingUrl;
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

            request({
                url: restUrl,
                method: 'GET',
                data: { month: month, year: year, context: simpleHotelCrm.context || 'frontend' },
                beforeSend: function() { $container.addClass('loading'); },
                success: function(response) {
                    if (response && response.html) {
                        $container.replaceWith(response.html);
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

        function closeModal() {
            var $modal = getModal();
            $modal.hide().attr('aria-hidden', 'true');
            $modal.find('.simple-hotel-crm-quick-booking-message').removeClass('error success').text('');
        }

        function openQuickBooking(bookingId) {
            var $modal = getModal();
            var $form = $modal.find('.simple-hotel-crm-quick-booking-form');
            var $message = $modal.find('.simple-hotel-crm-quick-booking-message');
            $message.removeClass('error success').text('Loading...');
            $modal.show().attr('aria-hidden', 'false');
            request({
                url: quickBookingUrl,
                method: 'GET',
                data: { booking_id: bookingId },
                success: function(response) {
                    if (!response || !response.id) {
                        $message.addClass('error').text('Failed to load booking.');
                        return;
                    }
                    $form.find('[name="booking_id"]').val(response.id);
                    $form.find('[name="guest_name"]').val(response.guest_name || '');
                    $form.find('[name="phone"]').val(response.phone || '');
                    $form.find('[name="contacted_date"]').val(response.contacted_date || '');
                    $form.find('[name="booking_note"]').val(response.booking_note || '');
                    $form.find('[name="booking_note"]').val(response.booking_note || '');
                    $form.find('[name="internal_notes"]').val(response.internal_notes || '');
                    var $status = $form.find('[name="status_code"]').empty();
                    $.each(response.status_options || {}, function(value, label) {
                        $('<option>').val(value).text(label).prop('selected', value === response.status_code).appendTo($status);
                    });
                    var $channel = $form.find('[name="source_channel"]').empty();
                    $.each(response.channel_options || {}, function(value, label) {
                        $('<option>').val(value).text(label).prop('selected', value === response.source_channel).appendTo($channel);
                    });
                    $modal.find('.simple-hotel-crm-open-full-booking').attr('href', response.detail_url || '#');
                    $message.text('');
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    $message.addClass('error').text('Failed to load booking.');
                }
            });
        }

        function saveExtras($input) {
            var payload = {
                booking_id: $input.data('booking-id'),
                room_id: $input.data('room-id'),
                reserved_room_id: $input.data('reserved-room-id'),
                extras_formula: $input.val() || ''
            };
            if (!payload.reserved_room_id) return;
            setSavingState($input, 'saving');
            request({
                url: bookingUrl,
                method: 'POST',
                data: payload,
                success: function(response) {
                    setSavingState($input, 'done');
                    var $editor = $input.closest('.calendar-extras-editor');
                    if (response && typeof response.extras_formula !== 'undefined') {
                        $input.val(response.extras_formula || '');
                    }
                    $editor.find('.calendar-extras-display').text(response && response.extras_total !== null && Number(response.extras_total) > 0 ? Number(response.extras_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €' : '');
                    $editor.removeClass('is-editing');
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    setSavingState($input, 'error');
                }
            });
        }

        $(document).on('click', '.quick-booking-trigger', function(e) {
            e.preventDefault();
            openQuickBooking($(this).data('booking-id'));
        });

        $(document).on('click', '.simple-hotel-crm-modal-backdrop, .simple-hotel-crm-modal-close', function() {
            closeModal();
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
            if (!href || simpleHotelCrm.context === 'admin') return;
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

        $(document).on('input', '.simple-hotel-crm-container .calendar-extras-input', function() {
            setSavingState($(this), 'done');
        });

        $(document).on('click', '.simple-hotel-crm-container .calendar-extras-display', function() {
            var $editor = $(this).closest('.calendar-extras-editor');
            $editor.addClass('is-editing');
            $editor.find('.calendar-extras-input').trigger('focus').trigger('select');
        });

        $(document).on('change blur', '.simple-hotel-crm-container .calendar-extras-input', function() {
            var $input = $(this);
            debounceSave('booking:' + $input.data('reserved-room-id'), function() { saveExtras($input); });
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

        (function() {
            var params = new URLSearchParams(window.location.search);
            var month = params.get('month');
            var year = params.get('year');
            if (month && year) {
                window.history.replaceState({ month: month, year: year }, '', window.location.href);
            }
        })();
    });
})(jQuery);
