<?php
/* @var $calendar_data array */
$rooms = $calendar_data['rooms'];
$matrix = $calendar_data['matrix'];
$month = $calendar_data['month'];
$year = $calendar_data['year'];
$days_in_month = $calendar_data['days_in_month'];
$days = $calendar_data['days'];
$daily_notes = $calendar_data['daily_notes'] ?? [];
$summary = simple_hotel_crm_build_daily_summary( $calendar_data );
$month_tabs = simple_hotel_crm_get_month_tabs( $month, $year );
$calendar_base_url = $calendar_base_url ?? '';
?>
<div class="simple-hotel-crm-container">
    <div class="calendar-month-tabs" role="tablist" aria-label="Calendar months">
        <?php foreach ( $month_tabs as $tab ) : ?>
            <a class="calendar-month-tab<?php echo $tab['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'month' => $tab['month'], 'year' => $tab['year'] ], $calendar_base_url ) ); ?>" data-month="<?php echo esc_attr( $tab['month'] ); ?>" data-year="<?php echo esc_attr( $tab['year'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="simple-hotel-crm">
        <table class="wp-list-table widefat fixed striped calendar-grid">
            <thead>
                <tr class="header-row month-row">
                    <th class="label sticky-col room-header-spacer"><span class="calendar-corner-month"><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $month, 1, $year ) ) ); ?></span></th>
                    <?php foreach ( $days as $day ) : ?>
                        <th>
                            <span class="calendar-day-number"><?php echo esc_html( $day ); ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr class="dow-row weekday-row">
                    <th class="label sticky-col room-header-spacer"></th>
                    <?php foreach ( $days as $day ) : $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day ); ?>
                        <th><span class="calendar-weekday"><?php echo esc_html( date_i18n( 'l', strtotime( $date_str ) ) ); ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr class="notes-row">
                    <td class="label sticky-col notes-label-cell"><span class="room-label-text"><?php esc_html_e( 'Notes', 'simple-hotel-crm' ); ?></span></td>
                    <?php foreach ( $days as $day ) : $note_date = sprintf( '%04d-%02d-%02d', $year, $month, $day ); ?>
                        <td class="calendar-cell notes-cell">
                            <input type="text" class="calendar-note-input" data-note-date="<?php echo esc_attr( $note_date ); ?>" value="<?php echo esc_attr( $daily_notes[ $note_date ] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Note for %s', 'simple-hotel-crm' ), $note_date ) ); ?>" />
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr><td colspan="<?php echo esc_attr( 1 + $days_in_month ); ?>"><?php esc_html_e( 'No rooms found.', 'simple-hotel-crm' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $rooms as $index => $room ) :
                        $room_id = $room->id;
                        $color = $room->color ?? '#ccc';
                        $room_numbers = [ 'ANE' => 1, 'DEL' => 2, 'LYS' => 3, 'TOU' => 4, 'TUL' => 5, 'COQ' => 0 ];
                        $room_number = $room_numbers[ $room->code ?? '' ] ?? ( $index + 1 );
                        $rows = [
                            [ 'label' => $room_number . ' - ' . $room->title, 'class' => 'room-name-row', 'type' => 'title', 'field' => 'booking_note' ],
                            [ 'label' => 'Name', 'class' => 'guest-row', 'type' => 'detail', 'field' => 'manual_guest_name', 'display_fn' => function( $b ) { return $b->guest_name ?? ''; } ],
                            [ 'label' => 'Channel', 'class' => 'channel-row', 'type' => 'detail', 'field' => '', 'display_fn' => function( $b ) { return $b->channel ?? ''; } ],
                            [ 'label' => 'Occupancy', 'class' => 'occupancy-row', 'type' => 'detail', 'field' => 'occupancy', 'display_fn' => function( $b ) { return $b->occupancy_str ?? ''; } ],
                            [ 'label' => 'Extras', 'class' => 'extras-row', 'type' => 'detail editable-text', 'field' => 'extras_formula', 'display_fn' => function( $b ) { return $b->extras_formula ?? ''; } ],
                            [ 'label' => 'Room rate', 'class' => 'tarif-row', 'type' => 'detail detail-tarif', 'field' => 'manual_tarif', 'display_fn' => function( $b ) { return isset( $b->tarif ) && '' !== $b->tarif && null !== $b->tarif ? number_format( (float) $b->tarif, 2, ',', ' ' ) . ' €' : ''; } ],
                            [ 'label' => 'Commission', 'class' => 'commission-row', 'type' => 'detail detail-commission', 'field' => 'manual_commission', 'display_fn' => function( $b ) { return isset( $b->commission ) && '' !== $b->commission && null !== $b->commission ? number_format( (float) $b->commission, 2, ',', ' ' ) . ' €' : ''; } ],
                        ];

                        foreach ( $rows as $row_index => $row ) :
                            $row_classes = [ $row['class'] ];
                            $row_classes[] = 0 === $row_index ? 'room-block-start' : 'room-block-detail';
                            if ( count( $rows ) - 1 === $row_index ) {
                                $row_classes[] = 'room-block-end';
                            }
                    ?>
                        <tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                            <td class="label sticky-col room-label-cell <?php echo esc_attr( $row['type'] ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>" style="--room-fill: <?php echo esc_attr( $color ); ?>;"><span class="room-label-text"><?php echo esc_html( $row['label'] ); ?></span></td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry['booking'] ?? null;
                                $display_value = '';
                                $booking_detail_url = '';
                                if ( $booking && isset( $row['display_fn'] ) && is_callable( $row['display_fn'] ) ) {
                                    $display_value = $row['display_fn']( $booking );
                                    $booking_detail_url = admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . absint( $booking->id ) );
                                }
                                $cell_classes = [ 'calendar-cell', $row['type'] ];
                                if ( ! empty( $booking ) ) {
                                    $cell_classes[] = 'has-booking';
                                }
                            ?>
                                <td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>" style="--room-fill: <?php echo esc_attr( $color ); ?>;">
                                    <?php if ( $booking && 'room-name-row' === $row['class'] ) : ?>
                                        <?php echo esc_html( $booking->booking_note ?? '' ); ?>
                                    <?php elseif ( $booking && 'guest-row' === $row['class'] ) : ?>
                                        <a class="calendar-booking-link quick-booking-trigger" href="<?php echo esc_url( $booking_detail_url ); ?>" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>"><?php echo esc_html( $display_value ); ?></a>
                                    <?php elseif ( $booking && 'occupancy-row' === $row['class'] ) : ?>
                                        <?php echo esc_html( $display_value ); ?>
                                    <?php elseif ( $booking && 'extras-row' === $row['class'] ) : ?>
                                        <?php echo null !== $booking->extras_total && '' !== $booking->extras_total && (float) $booking->extras_total > 0 ? esc_html( number_format( (float) $booking->extras_total, 2, ',', ' ' ) . ' €' ) : ''; ?>
                                    <?php elseif ( $booking && 'tarif-row' === $row['class'] ) : ?>
                                        <?php echo esc_html( $display_value ); ?>
                                    <?php elseif ( $booking && 'commission-row' === $row['class'] ) : ?>
                                        <?php echo esc_html( $display_value ); ?>
                                    <?php elseif ( $booking && 'invoice-row' === $row['class'] ) : ?>
                                        <button type="button" class="button button-small create-invoice-button" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>">
                                            <?php esc_html_e( 'Create Invoice', 'simple-hotel-crm' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <?php echo esc_html( $display_value ); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; endforeach; ?>
                <?php endif; ?>

                <tr class="calendar-spacer-row">
                    <td class="label sticky-col summary-label-cell"></td>
                    <?php foreach ( $days as $day ) : ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>

                <?php
                $footer_rows = [
                    'Income Day' => 'income_day',
                    'Adults' => 'tourist_tax_adults',
                    'Children' => 'tourist_tax_children',
                ];
                foreach ( $footer_rows as $label => $key ) :
                ?>
                    <tr class="summary-row summary-<?php echo esc_attr( sanitize_title( $key ) ); ?>">
                        <td class="label sticky-col summary-label-cell"><?php echo esc_html( $label ); ?></td>
                        <?php foreach ( $days as $day ) :
                            $value = $summary[ $day ][ $key ] ?? '';
                            $display = $value;
                            if ( in_array( $key, [ 'income_day', 'income_accumulated', 'booking_payment', 'booking_accumulated' ], true ) ) {
                                $display = number_format( (float) $value, 2, ',', ' ' ) . ' €';
                            }
                        ?>
                            <td><?php echo esc_html( (string) $display ); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="simple-hotel-crm-modal" style="display:none;" aria-hidden="true">
        <div class="simple-hotel-crm-modal-backdrop"></div>
        <div class="simple-hotel-crm-modal-dialog">
            <button type="button" class="simple-hotel-crm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'simple-hotel-crm' ); ?>">×</button>
            <h2><?php esc_html_e( 'Quick Edit Booking', 'simple-hotel-crm' ); ?></h2>
            <form class="simple-hotel-crm-quick-booking-form">
                <input type="hidden" name="booking_id" value="" />
                <input type="hidden" name="reserved_room_id" value="" />
                <input type="hidden" name="status_code" value="" />
                <div class="simple-hotel-crm-form-grid simple-hotel-crm-form-grid-2">
                    <p><label><?php esc_html_e( 'Guest name', 'simple-hotel-crm' ); ?><span class="simple-hotel-crm-copy-field"><input type="text" name="guest_name" class="regular-text" /><button type="button" class="button button-small simple-hotel-crm-copy-button" data-copy-target="guest_name"><?php esc_html_e( 'Copy', 'simple-hotel-crm' ); ?></button></span></label></p>
                    <p><label><?php esc_html_e( 'Phone', 'simple-hotel-crm' ); ?><span class="simple-hotel-crm-copy-field"><input type="text" name="phone" class="regular-text" /><button type="button" class="button button-small simple-hotel-crm-copy-button" data-copy-target="phone"><?php esc_html_e( 'Copy', 'simple-hotel-crm' ); ?></button></span></label></p>
                    <p><label><?php esc_html_e( 'Email', 'simple-hotel-crm' ); ?><span class="simple-hotel-crm-copy-field"><input type="email" name="email" class="regular-text" /><button type="button" class="button button-small simple-hotel-crm-copy-button" data-copy-target="email"><?php esc_html_e( 'Copy', 'simple-hotel-crm' ); ?></button></span></label></p>
                    <p><label><?php esc_html_e( 'Extras', 'simple-hotel-crm' ); ?><input type="text" name="extras_formula" class="regular-text" /></label></p>
                </div>
                <p><label><?php esc_html_e( 'Booking note', 'simple-hotel-crm' ); ?><br><input type="text" name="booking_note" class="regular-text" /></label></p>
                <p><label><?php esc_html_e( 'Internal notes', 'simple-hotel-crm' ); ?><br><textarea name="internal_notes" rows="5" class="large-text"></textarea></label></p>
                <p class="simple-hotel-crm-quick-booking-actions">
                    <a href="#" class="button simple-hotel-crm-open-full-booking"><?php esc_html_e( 'Open full booking', 'simple-hotel-crm' ); ?></a>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'simple-hotel-crm' ); ?></button>
                </p>
                <div class="simple-hotel-crm-quick-booking-message" aria-live="polite"></div>
            </form>
        </div>
    </div>
</div>
