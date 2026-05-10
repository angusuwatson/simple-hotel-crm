<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e( 'Merge Bookings', 'simple-hotel-crm' ); ?></h1>
    <form method="post">
        <?php wp_nonce_field( 'simple_hotel_crm_merge_bookings' ); ?>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox" id="select-all"></th>
                    <th class="manage-column" id="guest" scope="col"><?php esc_html_e( 'Guest', 'simple-hotel-crm' ); ?></th>
                    <th class="manage-column" id="dates" scope="col"><?php esc_html_e( 'Dates', 'simple-hotel-crm' ); ?></th>
                    <th class="manage-column" id="status" scope="col"><?php esc_html_e( 'Status', 'simple-hotel-crm' ); ?></th>
                    <th class="manage-column" id="channel" scope="col"><?php esc_html_e( 'Channel', 'simple-hotel-crm' ); ?></th>
                    <th class="manage-column" id="total" scope="col"><?php esc_html_e( 'Total', 'simple-hotel-crm' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $bookings as $booking ): ?>
                    <tr>
                        <th scope="row" class="check-column"><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr( $booking['id'] ); ?>"></th>
                        <td><?php echo esc_html( $booking['first_name'] . ' ' . $booking['last_name'] ); ?></td>
                        <td><?php echo esc_html( $booking['check_in_date'] . ' - ' . $booking['check_out_date'] ); ?></td>
                        <td><?php echo esc_html( $booking['status_code'] ); ?></td>
                        <td><?php echo esc_html( $booking['source_channel'] ); ?></td>
                        <td><?php echo esc_html( simple_hotel_crm_format_currency( $booking['total_amount'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="submit"><input type="submit" name="simple_hotel_crm_merge_bookings" class="button-primary" value="<?php esc_attr_e( 'Merge Selected Bookings', 'simple-hotel-crm' ); ?>"></p>
    </form>
</div>