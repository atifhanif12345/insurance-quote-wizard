<?php
/**
 * Validator
 * Server-side validation and sanitization for form fields.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Validator {

    /**
     * Validate entire submission against form config
     */
    public function validate_submission( $data, $config ) {
        $errors = array();

        if ( empty( $config['steps'] ) ) {
            return true;
        }

        $logic = new IQW_Conditional_Logic();

        foreach ( $config['steps'] as $step ) {
            if ( empty( $step['fields'] ) ) continue;

            // CHECK STEP-LEVEL CONDITIONS FIRST
            // If step is conditionally hidden, skip ALL its fields
            if ( ! empty( $step['conditions'] ) && ! empty( $step['conditions']['rules'] ) ) {
                if ( ! $logic->evaluate_conditions( $step['conditions'], $data ) ) {
                    continue; // Step is hidden, skip all fields
                }
            }

            foreach ( $step['fields'] as $field ) {
                $key   = $field['key'] ?? '';
                $type  = $field['type'] ?? 'text';
                $value = $data[ $key ] ?? '';

                // Skip non-input fields
                if ( in_array( $type, array( 'heading', 'paragraph' ), true ) ) continue;

                // Check field-level conditions
                if ( ! empty( $field['conditions'] ) && ! empty( $field['conditions']['rules'] ) ) {
                    if ( ! $logic->evaluate_conditions( $field['conditions'], $data ) ) {
                        continue; // Field is hidden, skip
                    }
                }

                // Repeater: validate sub-fields of first item
                if ( $type === 'repeater' ) {
                    if ( ! empty( $field['required'] ) && ! empty( $field['sub_fields'] ) ) {
                        // Check if at least first item has data
                        $has_data = false;
                        foreach ( $field['sub_fields'] as $sf ) {
                            $sub_key = $key . '_0_' . $sf['key'];
                            if ( ! $this->is_empty( $data[ $sub_key ] ?? '' ) ) {
                                $has_data = true;
                                break;
                            }
                        }
                        if ( ! $has_data ) {
                            $errors[ $key ] = sprintf(
                                __( '%s requires at least one entry.', 'iqw' ),
                                $field['label'] ?? $key
                            );
                        }
                    }
                    // Validate each sub-field of each repeater item
                    $count = intval( $data[ '__repeater_' . $key . '_count' ] ?? 1 );
                    for ( $ri = 0; $ri < $count; $ri++ ) {
                        foreach ( $field['sub_fields'] ?? array() as $sf ) {
                            $sub_key = $key . '_' . $ri . '_' . $sf['key'];
                            $sub_val = $data[ $sub_key ] ?? '';
                            if ( ! empty( $sf['required'] ) && $this->is_empty( $sub_val ) ) {
                                $errors[ $sub_key ] = sprintf(
                                    __( '%s #%d: %s is required.', 'iqw' ),
                                    $field['label'] ?? $key, $ri + 1, $sf['label'] ?? $sf['key']
                                );
                            }
                        }
                    }
                    continue;
                }

                // Address: validate sub-fields (street, city, state, zip)
                if ( $type === 'address' ) {
                    if ( ! empty( $field['required'] ) ) {
                        foreach ( array( 'street', 'city', 'state', 'zip' ) as $sub ) {
                            $sub_key = $key . '_' . $sub;
                            if ( $this->is_empty( $data[ $sub_key ] ?? '' ) ) {
                                $errors[ $sub_key ] = sprintf(
                                    __( '%s is required.', 'iqw' ),
                                    ucfirst( $sub )
                                );
                            }
                        }
                    }
                    continue;
                }

                // Check required
                if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
                    $errors[ $key ] = sprintf(
                        __( '%s is required.', 'iqw' ),
                        $field['label'] ?? $key
                    );
                    continue;
                }

                // Skip validation if empty and not required
                if ( $this->is_empty( $value ) ) continue;

                // Type-specific validation
                $type_error = $this->validate_field_type( $field, $value );
                if ( $type_error ) {
                    $errors[ $key ] = $type_error;
                }
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', __( 'Please fix the errors below.', 'iqw' ), $errors );
        }

        return true;
    }

    /**
     * Validate a single field by its type
     */
    private function validate_field_type( $field, $value ) {
        $type = $field['type'] ?? 'text';

        switch ( $type ) {
            case 'email':
                if ( ! is_email( $value ) ) {
                    return __( 'Please enter a valid email address.', 'iqw' );
                }
                break;

            case 'phone':
                $phone = preg_replace( '/[^\d]/', '', $value );
                if ( strlen( $phone ) < 10 || strlen( $phone ) > 11 ) {
                    return __( 'Please enter a valid phone number.', 'iqw' );
                }
                break;

            case 'number':
            case 'currency':
                $num = preg_replace( '/[^\d.]/', '', $value );
                if ( ! is_numeric( $num ) ) {
                    return __( 'Please enter a valid number.', 'iqw' );
                }
                if ( isset( $field['min'] ) && $num < $field['min'] ) {
                    return sprintf( __( 'Minimum value is %s.', 'iqw' ), $field['min'] );
                }
                if ( isset( $field['max'] ) && $num > $field['max'] ) {
                    return sprintf( __( 'Maximum value is %s.', 'iqw' ), $field['max'] );
                }
                break;

            case 'date':
                if ( ! $this->validate_date( $value ) ) {
                    return __( 'Please enter a valid date.', 'iqw' );
                }
                // Age validation for DOB fields
                if ( ! empty( $field['validation'] ) && $field['validation'] === 'dob_driver' ) {
                    $age = $this->calculate_age( $value );
                    if ( $age < 15 || $age > 100 ) {
                        return __( 'Driver must be between 15 and 100 years old.', 'iqw' );
                    }
                }
                break;

            case 'select':
            case 'radio':
            case 'radio_cards':
                if ( ! empty( $field['options'] ) ) {
                    $valid_values = array_column( $field['options'], 'value' );
                    if ( ! in_array( $value, $valid_values, true ) ) {
                        return __( 'Please select a valid option.', 'iqw' );
                    }
                }
                break;

            case 'url':
                if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return __( 'Please enter a valid URL.', 'iqw' );
                }
                break;

            case 'consent':
                if ( ! empty( $field['required'] ) && $value !== 'yes' ) {
                    return __( 'You must agree to continue.', 'iqw' );
                }
                break;

            case 'checkbox_group':
                if ( is_array( $value ) && ! empty( $field['options'] ) ) {
                    $valid_values = array_column( $field['options'], 'value' );
                    foreach ( $value as $v ) {
                        if ( ! in_array( $v, $valid_values, true ) ) {
                            return __( 'Invalid option selected.', 'iqw' );
                        }
                    }
                }
                break;

            case 'text':
                if ( isset( $field['validation'] ) ) {
                    switch ( $field['validation'] ) {
                        case 'zip':
                            if ( ! preg_match( '/^\d{5}(-\d{4})?$/', $value ) ) {
                                return __( 'Please enter a valid ZIP code.', 'iqw' );
                            }
                            break;
                        case 'vin':
                            if ( strlen( $value ) > 0 && ! preg_match( '/^[A-HJ-NPR-Z0-9*]{10,17}$/i', $value ) ) {
                                return __( 'Please enter a valid VIN.', 'iqw' );
                            }
                            break;
                    }
                }
                break;
        }

        return null; // No error
    }

    /**
     * Sanitize all submitted fields and remove conditionally hidden field data
     */
    public function sanitize_fields( $data, $config ) {
        $clean = array();
        $logic = new IQW_Conditional_Logic();

        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );

            // Find field config to determine type and check conditions
            $field_config = $this->find_field_config( $key, $config );

            // Skip conditionally hidden fields - don't save stale data
            if ( ! empty( $field_config['conditions'] ) && ! empty( $field_config['conditions']['rules'] ) ) {
                if ( ! $logic->evaluate_conditions( $field_config['conditions'], $data ) ) {
                    continue;
                }
            }

            // Also check if the field's entire step is hidden
            $step_config = $this->find_step_for_field( $key, $config );
            if ( $step_config && ! empty( $step_config['conditions'] ) && ! empty( $step_config['conditions']['rules'] ) ) {
                if ( ! $logic->evaluate_conditions( $step_config['conditions'], $data ) ) {
                    continue;
                }
            }

            if ( is_array( $value ) ) {
                $clean[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $type = $field_config['type'] ?? 'text';

                switch ( $type ) {
                    case 'email':
                        $clean[ $key ] = sanitize_email( $value );
                        break;
                    case 'textarea':
                        $clean[ $key ] = sanitize_textarea_field( $value );
                        break;
                    case 'number':
                    case 'currency':
                        $clean[ $key ] = preg_replace( '/[^\d.]/', '', $value );
                        break;
                    case 'phone':
                        $clean[ $key ] = preg_replace( '/[^\d\-\(\)\s\+]/', '', $value );
                        break;
                    case 'url':
                        $clean[ $key ] = esc_url_raw( $value );
                        break;
                    default:
                        $clean[ $key ] = sanitize_text_field( $value );
                }
            }
        }

        return $clean;
    }

    /**
     * Find field config by key
     */
    private function find_field_config( $key, $config ) {
        if ( empty( $config['steps'] ) ) return array();

        foreach ( $config['steps'] as $step ) {
            if ( empty( $step['fields'] ) ) continue;
            foreach ( $step['fields'] as $field ) {
                if ( ( $field['key'] ?? '' ) === $key ) {
                    return $field;
                }
                // Repeater sub-field match: key pattern is {repeater_key}_{index}_{sub_key}
                if ( ( $field['type'] ?? '' ) === 'repeater' && ! empty( $field['sub_fields'] ) ) {
                    foreach ( $field['sub_fields'] as $sf ) {
                        // Match: starts with "{repeater_key}_" and ends with "_{sub_key}"
                        if ( preg_match( '/^' . preg_quote( $field['key'], '/' ) . '_\d+_' . preg_quote( $sf['key'], '/' ) . '$/', $key ) ) {
                            return array_merge( $sf, array( '_repeater_parent' => $field['key'] ) );
                        }
                    }
                }
            }
        }

        return array();
    }

    /**
     * Find the step config that contains a field by key
     */
    private function find_step_for_field( $key, $config ) {
        if ( empty( $config['steps'] ) ) return null;

        foreach ( $config['steps'] as $step ) {
            if ( empty( $step['fields'] ) ) continue;
            foreach ( $step['fields'] as $field ) {
                if ( ( $field['key'] ?? '' ) === $key ) {
                    return $step;
                }
                // Repeater sub-field match
                if ( ( $field['type'] ?? '' ) === 'repeater' && ! empty( $field['sub_fields'] ) ) {
                    foreach ( $field['sub_fields'] as $sf ) {
                        if ( preg_match( '/^' . preg_quote( $field['key'], '/' ) . '_\d+_' . preg_quote( $sf['key'], '/' ) . '$/', $key ) ) {
                            return $step;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if a field is visible based on conditional logic
     */
    private function is_field_visible( $field, $data ) {
        if ( empty( $field['conditions'] ) ) {
            return true;
        }

        $logic = new IQW_Conditional_Logic();
        return $logic->evaluate_conditions( $field['conditions'], $data );
    }

    /**
     * Check if value is empty
     */
    private function is_empty( $value ) {
        if ( is_array( $value ) ) {
            return empty( $value );
        }
        return trim( (string) $value ) === '';
    }

    /**
     * Validate date format
     */
    private function validate_date( $date ) {
        $formats = array( 'Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y' );
        foreach ( $formats as $format ) {
            $d = DateTime::createFromFormat( $format, $date );
            if ( $d && $d->format( $format ) === $date ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate age from date of birth
     */
    private function calculate_age( $dob ) {
        try {
            $birth = new DateTime( $dob );
            $now   = new DateTime();
            return $now->diff( $birth )->y;
        } catch ( Exception $e ) {
            return 0;
        }
    }
}
