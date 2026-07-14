<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWN_Jalali {
    /**
     * Get the Jalali date string for a WooCommerce order.
     */
    public static function order_date( WC_Order $order ) {
        $dt = $order->get_date_created();
        if ( ! $dt ) return '';

        $ts = $dt->getTimestamp();
        return self::timestamp_to_jalali( $ts );
    }

    /**
     * Convert timestamp to Jalali string.
     */
    public static function timestamp_to_jalali( $timestamp ) {
        $gy = (int) wp_date( 'Y', $timestamp );
        $gm = (int) wp_date( 'n', $timestamp );
        $gd = (int) wp_date( 'j', $timestamp );

        return self::from_gregorian( $gy, $gm, $gd );
    }

    /**
     * Get today's Jalali date string.
     */
    public static function today() {
        return self::timestamp_to_jalali( current_time( 'timestamp' ) );
    }

    /**
     * Gregorian → Jalali (jdf standard algorithm).
     */
    public static function from_gregorian( $gy, $gm, $gd ) {
        $g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

        if ( $gy > 1600 ) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;

        $days = ( 365 * $gy )
              + intval( ( $gy2 + 3 ) / 4 )
              - intval( ( $gy2 + 99 ) / 100 )
              + intval( ( $gy2 + 399 ) / 400 )
              - 80
              + $gd
              + $g_d_m[ $gm - 1 ];

        $jy += 33 * intval( $days / 12053 );
        $days %= 12053;

        $jy += 4 * intval( $days / 1461 );
        $days %= 1461;

        if ( $days > 365 ) {
            $jy += intval( ( $days - 1 ) / 365 );
            $days = ( $days - 1 ) % 365;
        }

        if ( $days < 186 ) {
            $jm = 1 + intval( $days / 31 );
            $jd = 1 + ( $days % 31 );
        } else {
            $jm = 7 + intval( ( $days - 186 ) / 30 );
            $jd = 1 + ( ( $days - 186 ) % 30 );
        }

        return self::format( $jy, $jm, $jd );
    }

    /**
     * Format Jalali date into standard readable string.
     */
    private static function format( $jy, $jm, $jd ) {
        $months = array(
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        );
        return "{$jd} {$months[ $jm - 1 ]} {$jy}";
    }
}
