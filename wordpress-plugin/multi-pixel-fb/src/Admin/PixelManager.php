<?php
/**
 * Pixel Manager Class
 *
 * Handles CRUD operations for Facebook Pixel configurations
 *
 * @package MultiPixelFB
 */

namespace MultiPixelFB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PixelManager class
 */
class PixelManager {

    /**
     * Option name for storing pixels
     *
     * @var string
     */
    const OPTION_NAME = 'mpfb_pixels';

    /**
     * Get all configured pixels
     *
     * @return array Array of pixel configurations
     */
    public static function get_pixels() {
        $pixels = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $pixels ) ) {
            $pixels = array();
        }

        return $pixels;
    }

    /**
     * Get enabled pixels only
     *
     * @return array Array of enabled pixel configurations
     */
    public static function get_enabled_pixels() {
        $pixels = self::get_pixels();

        return array_filter( $pixels, function( $pixel ) {
            return isset( $pixel['enabled'] ) && $pixel['enabled'];
        });
    }

    /**
     * Get a single pixel by ID
     *
     * @param string $pixel_id Pixel ID
     * @return array|null Pixel configuration or null if not found
     */
    public static function get_pixel( $pixel_id ) {
        $pixels = self::get_pixels();

        foreach ( $pixels as $pixel ) {
            if ( isset( $pixel['pixel_id'] ) && $pixel['pixel_id'] === $pixel_id ) {
                return $pixel;
            }
        }

        return null;
    }

    /**
     * Add a new pixel
     *
     * @param array $pixel_data Pixel configuration data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function add_pixel( $pixel_data ) {
        // Validate required fields
        $validation = self::validate_pixel_data( $pixel_data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $pixels = self::get_pixels();

        // Check if pixel already exists
        foreach ( $pixels as $pixel ) {
            if ( $pixel['pixel_id'] === $pixel_data['pixel_id'] ) {
                return new \WP_Error(
                    'pixel_exists',
                    __( 'Um pixel com este ID já existe.', 'multi-pixel-fb' )
                );
            }
        }

        // Add timestamps
        $pixel_data['created_at'] = current_time( 'timestamp' );
        $pixel_data['updated_at'] = current_time( 'timestamp' );

        // Add pixel
        $pixels[] = $pixel_data;

        return update_option( self::OPTION_NAME, $pixels );
    }

    /**
     * Update an existing pixel
     *
     * @param string $pixel_id Pixel ID
     * @param array $pixel_data New pixel configuration data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function update_pixel( $pixel_id, $pixel_data ) {
        // Validate required fields
        $validation = self::validate_pixel_data( $pixel_data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $pixels = self::get_pixels();
        $found = false;

        foreach ( $pixels as $key => $pixel ) {
            if ( $pixel['pixel_id'] === $pixel_id ) {
                // Preserve created_at timestamp
                if ( isset( $pixel['created_at'] ) ) {
                    $pixel_data['created_at'] = $pixel['created_at'];
                }

                $pixel_data['updated_at'] = current_time( 'timestamp' );
                $pixels[ $key ] = $pixel_data;
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error(
                'pixel_not_found',
                __( 'Pixel não encontrado.', 'multi-pixel-fb' )
            );
        }

        return update_option( self::OPTION_NAME, $pixels );
    }

    /**
     * Delete a pixel
     *
     * @param string $pixel_id Pixel ID
     * @return bool True on success, false on failure
     */
    public static function delete_pixel( $pixel_id ) {
        $pixels = self::get_pixels();
        $new_pixels = array();

        foreach ( $pixels as $pixel ) {
            if ( $pixel['pixel_id'] !== $pixel_id ) {
                $new_pixels[] = $pixel;
            }
        }

        return update_option( self::OPTION_NAME, $new_pixels );
    }

    /**
     * Validate pixel data
     *
     * @param array $pixel_data Pixel configuration data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_pixel_data( $pixel_data ) {
        // Required fields
        $required_fields = array( 'pixel_id', 'access_token' );

        foreach ( $required_fields as $field ) {
            if ( empty( $pixel_data[ $field ] ) ) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(
                        /* translators: %s: field name */
                        __( 'Campo obrigatório ausente: %s', 'multi-pixel-fb' ),
                        $field
                    )
                );
            }
        }

        // Validate pixel_id format (should be numeric)
        if ( ! is_numeric( $pixel_data['pixel_id'] ) ) {
            return new \WP_Error(
                'invalid_pixel_id',
                __( 'Pixel ID deve ser numérico.', 'multi-pixel-fb' )
            );
        }

        // Validate execution_mode
        if ( isset( $pixel_data['execution_mode'] ) ) {
            $valid_modes = array( 'parallel', 'sequential' );
            if ( ! in_array( $pixel_data['execution_mode'], $valid_modes, true ) ) {
                return new \WP_Error(
                    'invalid_execution_mode',
                    __( 'Modo de execução inválido. Use "parallel" ou "sequential".', 'multi-pixel-fb' )
                );
            }
        }

        return true;
    }

    /**
     * Sanitize pixel data
     *
     * @param array $pixel_data Raw pixel data
     * @return array Sanitized pixel data
     */
    public static function sanitize_pixel_data( $pixel_data ) {
        return array(
            'pixel_id' => sanitize_text_field( $pixel_data['pixel_id'] ?? '' ),
            'access_token' => sanitize_text_field( $pixel_data['access_token'] ?? '' ),
            'test_event_code' => sanitize_text_field( $pixel_data['test_event_code'] ?? '' ),
            'partner_agent' => sanitize_text_field( $pixel_data['partner_agent'] ?? '' ),
            'execution_mode' => in_array( $pixel_data['execution_mode'] ?? 'parallel', array( 'parallel', 'sequential' ), true )
                ? $pixel_data['execution_mode']
                : 'parallel',
            'enabled' => ! empty( $pixel_data['enabled'] ),
            'priority' => absint( $pixel_data['priority'] ?? 0 ),
            'description' => sanitize_textarea_field( $pixel_data['description'] ?? '' ),
        );
    }

    /**
     * Get pixel count
     *
     * @return int Number of configured pixels
     */
    public static function get_pixel_count() {
        return count( self::get_pixels() );
    }

    /**
     * Get enabled pixel count
     *
     * @return int Number of enabled pixels
     */
    public static function get_enabled_pixel_count() {
        return count( self::get_enabled_pixels() );
    }

    /**
     * Clear pixel cache
     */
    public static function clear_cache() {
        delete_transient( 'mpfb_pixel_cache' );
    }
}
