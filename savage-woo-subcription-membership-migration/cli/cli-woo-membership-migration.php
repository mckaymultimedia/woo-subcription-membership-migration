<?php
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Woo_Membership_Migration
{
    /**
     * Run the woo membership migration command.
     *
     * ## OPTIONS
     *     wp woo-subscription-membership-migration run
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function run($args, $assoc_args)
    {
        $new_memberships = [
            9004111231142679 => ['plan_id' => 9004111231184909, 'plan_name' => 'Gold Membership'],
            9004111231142680 => ['plan_id' => 9004111231184909, 'plan_name' => 'Gold Membership'],
            9004111231142681 => ['plan_id' => 9004111231199160, 'plan_name' => 'Platinum Membership'],
            9004111231142682 => ['plan_id' => 9004111231199160, 'plan_name' => 'Platinum Membership'],
            9004111231142683 => ['plan_id' => 9004111231199174, 'plan_name' => 'Diamond Membership'],
            9004111231142684 => ['plan_id' => 9004111231199174, 'plan_name' => 'Diamond Membership'],
        ];


        // Display your settings page content here
        $csvFilePath =  plugins_url('/import-data48.csv', __FILE__);

        $columnsToRead = ['subscription_id', 'sub_prod_id', 'sub_var_id', 'destination_product_id', 'destination_variation_id'];

        $file = fopen($csvFilePath, 'r');

        if ($file) {
            $header = fgetcsv($file);
            $columnIndices = [];
            foreach ($columnsToRead as $column) {
                $columnIndices[] = array_search($column, $header);
            }

            while (($row = fgetcsv($file)) !== false) {
                $rowData = [];

                foreach ($columnIndices as $index) {
                    $rowData[] = $row[$index];
                }
                if (class_exists('WooCommerce') && is_numeric($rowData[3])) {
                    $old_subscription_id = $rowData[0];
                    $old_product_id = $rowData[1];
                    $old_variation_id = $rowData[2];
                    $new_product_id = $rowData[3];
                    $new_variation_id = $rowData[4];
                    $new_plan_id = $new_memberships[$new_variation_id]['plan_id'];
                    $new_plan_name = $new_memberships[$new_variation_id]['plan_name'];

                    WP_CLI::success("File subscription ID: $old_subscription_id File Variation ID: $old_variation_id");

                    $old_subscription = wcs_get_subscription($old_subscription_id);

                    if ($old_subscription && $old_subscription->get_status() == 'active') {

                        $related_orders_ids_array = $old_subscription->get_related_orders();
                        // Sort the related orders IDs in descending order by ID (latest first)
                        arsort($related_orders_ids_array);
                        // Get the ID of the latest order
                        $latest_order_id = reset($related_orders_ids_array);

                        // Check if there is a latest order
                        if ($latest_order_id) {

                            $order = wc_get_order($latest_order_id);
                            $items = $order->get_items();

                            $have_variation = false;
                            foreach ($items as $item) {

                                $woo_product_id = $item->get_id();
                                $woo_variation_id = $item->get_variation_id();

                                WP_CLI::success("Woo Variation ID: $woo_variation_id Item ID: $woo_product_id ");

                                if ($woo_variation_id == $old_variation_id || $woo_product_id == $old_variation_id || $woo_product_id == $old_product_id) {

                                    $item_total     = $item->get_total();
                                    $item_total_tax = $item->get_total_tax();
                                    $item_quantity  = $item->get_quantity();
                                    $item_subtotal  = $item->get_subtotal();

                                    $order_o = $order = wc_get_order($old_subscription_id);
                                    $order_shipping_tax = $order_o->get_shipping_total();

                                    $product_details = [
                                        'item_total' => $item_total,
                                        'item_subtotal' => $item_subtotal,
                                        'item_quantity' => $item_quantity,
                                        'order_shipping_tax' => $order_shipping_tax
                                    ];

                                    $have_variation = true;
                                    break;
                                }
                            }
                            if ($have_variation) {

                                WP_CLI::success("Woo Shipping Tax: " . $product_details['order_shipping_tax']);

                                $user_id = $old_subscription->get_user_id();
                                $variation = wc_get_product($new_variation_id);
                                $variation_attributes = $variation->get_variation_attributes();
                                $attribute_billing = $variation_attributes['attribute_billing'];

                                WP_CLI::success("User ID: $user_id ");
                                WP_CLI::success("New Product ID: $new_product_id ");
                                WP_CLI::success("New Variation ID: $new_variation_id ");
                                WP_CLI::success("New Plan ID: $new_plan_id ");
                                WP_CLI::success("New Plan Name: $new_plan_name ");
                                WP_CLI::success("Attribute Billing: $attribute_billing ");
                                WP_CLI::success("Old Subscription ID: $old_subscription_id ");

                                if ($user_id) {
                                    $memberships = wc_memberships_get_memberships_from_subscription($old_subscription_id);
                                    if ($memberships) {
                                        foreach ($memberships as $membership) {
                                            $membership_id = $membership->get_id();
                                            $membership_subscription_id = get_post_meta($membership->id, '_subscription_id');
                                            WP_CLI::success("Checking membership: $membership_id ");
                                            if ($old_subscription_id == $membership_subscription_id[0]) {
                                                $membership_status = $membership->get_status();
                                                if ($membership_status == 'active') {
                                                    WP_CLI::success("Called update_membership_subscription function ");
                                                    $this->update_membership_subscription($user_id, $membership, $new_product_id, $new_variation_id, $new_plan_id, $new_plan_name, $attribute_billing, $old_subscription_id, $product_details);
                                                } else {
                                                    $membership = '';
                                                    WP_CLI::success("Called update_membership_subscription function also create membership");
                                                    $this->update_membership_subscription($user_id, $membership, $new_product_id, $new_variation_id, $new_plan_id, $new_plan_name, $attribute_billing, $old_subscription_id, $product_details);
                                                }
                                            }
                                        }
                                    } else {
                                        $membership = '';
                                        WP_CLI::success("Called update_membership_subscription function also create membership");
                                        $this->update_membership_subscription($user_id, $membership, $new_product_id, $new_variation_id, $new_plan_id, $new_plan_name, $attribute_billing, $old_subscription_id, $product_details);
                                    }
                                } else {
                                    WP_CLI::success("No user linked with this subscription");
                                }
                            } else {
                                WP_CLI::success("Variation id not exist ");
                            }
                        } else {
                            WP_CLI::success("No orders found for this subscription. ");
                        }
                    } else {
                        WP_CLI::success("Subscription doesn't exist Or status not active");
                    }
                    WP_CLI::success("--------------------------------------------------");
                }
            }

            fclose($file);
        } else {
            WP_CLI::success("Error opening file ");
        }
    }


    public function update_membership_subscription($user_id, $membership, $new_product_id, $new_variation_id, $new_plan_id, $new_plan_name, $attribute_billing, $old_subscription_id, $product_details)
    {
        global $wpdb;

        $plan = $attribute_billing;
        $new_product_id = $new_product_id;
        $variation_id = $new_variation_id;

        WP_CLI::success("User ID: $user_id ");

        if ($membership) {
            $start_date = get_post_meta($membership->id, '_start_date');
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $start_date[0]);
            $new_date = $date->format('Y-m-d H:i:s');
        } else {
            $current_date = new DateTime();
            $new_date = $current_date->format('Y-m-d H:i:s');
        }

        $parent_order_id = 0;

        $old_subscription = wcs_get_subscription($old_subscription_id);

        $parent_order_id = $old_subscription->get_parent_id();

        // Add shipping product to the subscription
        $shipping_fee = $product_details['order_shipping_tax'];

        if (is_a($old_subscription, 'WC_Subscription')) {

            $get_data = $old_subscription->get_data();
            $all_meta_data = get_post_meta($old_subscription_id);

            WP_CLI::success("User old subscription ID: $old_subscription_id ");

            $product = wc_get_product($new_product_id);
            $variation = wc_get_product($variation_id);

            $variation_data = [
                'variation_id'   => $variation_id,
                'variation'      => $variation->get_variation_attributes(),
                'product_id'     =>  $new_product_id,
                'quantity'       => (float)$product_details['item_quantity'],
                'subtotal'       => $variation->get_price(),
                'total'          => (float)$product_details['item_total'],
                'line_total'     => $variation->get_price(),
                'line_subtotal'  => $variation->get_price(),
            ];

            if ($plan == 'Monthly') {
                $plan = 'month';
            } else {
                $plan = 'year';
            }

            WP_CLI::success("User subcription plan: $plan ");

            $new_subscription = wcs_create_subscription([
                'order_date' => current_time('mysql'),
                'status' => 'active',
                'customer_id' => $user_id,
                'billing_period' => $plan,
                'billing_interval' => 1,
                'start_date' => $new_date,
                'order_id' => $parent_order_id
            ]);

            $new_subscription->add_product($product, 1, $variation_data);

            $new_subscription->calculate_totals();
            $new_subscription_id = $new_subscription->save();

            // Add shipping product to the subscription
            if ($shipping_fee) {
                $item_id = wc_add_order_item($new_subscription_id, array('order_item_name' => __('Shipping Fee', 'cdashmm'), 'order_item_type' => 'shipping'));
                if ($item_id) {
                    wc_add_order_item_meta($item_id, 'cost', $shipping_fee);
                    wc_add_order_item_meta($item_id, '_line_total', $shipping_fee);
                    wc_add_order_item_meta($item_id, '_line_tax', 0);
                    wc_add_order_item_meta($item_id, '_line_subtotal', $shipping_fee);
                    wc_add_order_item_meta($item_id, '_line_subtotal_tax', 0);
                }
            }

            // SQL query to update post_parent based on conditions
            $query = $wpdb->prepare("UPDATE {$wpdb->posts}
                            SET post_parent = $parent_order_id
                            WHERE ID = $new_subscription_id");

            // Execute the SQL query
            $result = $wpdb->query($query);

            if (false !== $result) {
                WP_CLI::success("Parent Order Id is updated for new subscription: $new_subscription_id ");
            } else {
                WP_CLI::success("Cannot update the parent order ID for new subscription! ");
            }

            foreach ($all_meta_data as $key => $value) {
                if (is_array($value)) {
                    $unserialized_data = maybe_unserialize($value[0]);
                    update_post_meta($new_subscription_id, $key, $unserialized_data);
                } else {
                    $unserialized_data = maybe_unserialize($value);
                    update_post_meta($new_subscription_id, $key, $unserialized_data);
                }
            }
            $new_subscription = wcs_get_subscription($new_subscription_id);
            WP_CLI::success("Before calculate_totals");
            $new_subscription->calculate_totals();
            $new_subscription->save();
            WP_CLI::success("Update all meta data for new subscription ");
        }

        $date = new DateTime(null, new DateTimeZone(date_default_timezone_get()));
        $date->setTimeZone(new DateTimeZone('America/Chicago'));
        $migration_date =  $date->format('Y-m-d h:i:s a');

        $old_membership_name = ($membership) ? $membership->get_plan()->get_name() : "Old not exist";

        add_post_meta($new_subscription_id, "_v2_migration_date", $migration_date);
        add_post_meta($new_subscription_id, "_v2_old_membership_name", $old_membership_name);
        add_post_meta($new_subscription_id, "_v2_old_subscription", $old_subscription_id);
        add_post_meta($new_subscription_id, "_v2_new_membership_name", $new_plan_name);
        add_post_meta($new_subscription_id, "_v2_new_subscription", $new_subscription_id);
        add_post_meta($new_subscription_id, "_v2_plan", $attribute_billing);
        add_post_meta($new_subscription_id, "_v2_user_id", $user_id);



        if ($membership) {
            $memership_id = $membership->id;
        } else {
            $membership = wc_memberships_create_user_membership(array(
                'user_id' => $user_id,
                'plan_id' => $new_plan_id,
            ));
            $memership_id = $membership->get_id();
        }

        $post_data = array(
            'ID' => $memership_id,
            'post_parent' => $new_plan_id,
        );

        // Update the post
        $update_result = wp_update_post($post_data);

        update_post_meta($memership_id, '_product_id', $variation_id);
        update_post_meta($memership_id, '_order_id', $parent_order_id);
        update_post_meta($memership_id, '_subscription_id', $new_subscription_id);
        update_post_meta($memership_id, '_start_date', $new_date);
        update_post_meta($memership_id, '_end_date', '');

        if ($update_result !== 0 && !is_wp_error($update_result)) {
            WP_CLI::success("Membership: $memership_id post parent updated successfully. New parent ID: $new_plan_id ");
        } else {
            // Get error message if update failed
            $error_message = is_wp_error($update_result) ? $update_result->get_error_message() : 'Unknown error.';
            WP_CLI::success("Membership post parent update failed. Error: $error_message ");
        }

        // Load the source subscription object
        $source_subscription = wcs_get_subscription($old_subscription_id);

        // Load the target subscription object
        $target_subscription = wcs_get_subscription($new_subscription_id);

        // Check if both subscriptions exist
        if ($source_subscription && $target_subscription) {
            // Get next payment date from the source subscription

            $subs_renewal = get_post_meta($old_subscription_id, '_schedule_next_payment');

            if ($subs_renewal && is_array($subs_renewal)) {
                $timezone = 'America/Chicago';

                $trial_end = get_post_meta($old_subscription_id, '_schedule_trial_end');

                $check_trial = false;
                if ($trial_end && is_array($trial_end)) {
                    if (array_key_exists(0, $trial_end)) {
                        if ($trial_end[0] != 0) {
                            $check_trial = true;
                        }
                    }
                }

                if (array_key_exists(0, $subs_renewal)) {
                    WP_CLI::success("Next payment date " . $subs_renewal[0]);

                    $subs_renewal_date = $subs_renewal[0];
                    if ($check_trial) {
                        $date_time_adjust = new DateTime($subs_renewal_date);
                        $date_time_adjust->modify('+2 minutes');
                        $subs_renewal_date = $date_time_adjust->format('Y-m-d H:i:s');

                        $target_subscription->update_dates(array(
                            'next_payment' => $subs_renewal_date,
                        ), $timezone);

                        $target_subscription->update_dates(array(
                            'trial_end' => $trial_end[0],
                        ), $timezone);
                    } else {
                        $target_subscription->update_dates(array(
                            'next_payment' => $subs_renewal_date,
                        ), $timezone);
                    }

                    $target_subscription->calculate_date('next_payment');
                }

                $payment_method = $source_subscription->get_payment_method();

                // Set payment details for the new subscription
                $target_subscription->set_payment_method($payment_method);

                // Save changes
                $target_subscription->save();

                $old_subscription->update_status('active');

                WP_CLI::success("Next payment date updated successfully.");
            } else {
                WP_CLI::success("Next payment date not set or source subscription is inactive.");
            }
        } else {
            WP_CLI::success("Source or target subscription not found.");
        }

        $old_subscription->update_status('cancelled');

        WP_CLI::success("User old subscription is cancelled: $old_subscription_id ");
    }
}

WP_CLI::add_command('woo-subscription-membership-migration', 'Woo_Membership_Migration');
