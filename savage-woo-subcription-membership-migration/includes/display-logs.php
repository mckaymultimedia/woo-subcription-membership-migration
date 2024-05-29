<?php
function add_admin_menu_item()
{
    add_menu_page(
        'Membership Migration Logs', // Page title
        'Migration Logs', // Menu title
        'manage_options', // Capability
        'savage-membership-migration', // Menu slug
        'savage_mebership_log_page', // Callback function to display the settings page
        'dashicons-admin-generic' // Icon
    );
}
add_action('admin_menu', 'add_admin_menu_item');

// Display the settings page
function savage_mebership_log_page()
{
    // Display the settings page content
echo '<h1>Savage - Migrated Memberships</h1>';

// Pagination variables
$items_per_page = 20;
$current_page = isset($_GET['page_no']) ? max(1, intval($_GET['page_no'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Query posts and metadata
global $wpdb;
$query = $wpdb->prepare("
SELECT DISTINCT p.ID
FROM {$wpdb->posts} p
INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
WHERE m.meta_key = '_v2_migration_date'
ORDER BY m.meta_value DESC
LIMIT $items_per_page OFFSET $offset");

$results = $wpdb->get_results($query);

// Display the results in a table
if ($results) {
    echo '<table class="wp-list-table widefat fixed striped table-view-list ">';
    echo '<tr>';
    echo '<th><strong>User ID</strong></th>';
    echo '<th><strong>Old Membership</strong></th>';
    echo '<th><strong>New Membership</strong></th>';
    echo '<th><strong>Old Subscription</strong></th>';
    echo '<th><strong>New Subscription</strong></th>';
    echo '<th><strong>Billing Interval</strong></th>';
    echo '<th><strong>Migration Date</strong></th>';
    echo '</tr>';

    foreach ($results as $result) {

        $_old_membership_name =  get_post_meta($result->ID, '_v2_old_membership_name');
        $_new_membership_name =  get_post_meta($result->ID, '_v2_new_membership_name');
        $_old_subscription =  get_post_meta($result->ID, '_v2_old_subscription');
        $_new_subscription =  get_post_meta($result->ID, '_v2_new_subscription');
        $_plan =  get_post_meta($result->ID, '_v2_plan');
        $_migration_date =  get_post_meta($result->ID, '_v2_migration_date');
        $_user_id =  get_post_meta($result->ID, '_v2_user_id');

        $_old_membership_name =  (!empty($_old_membership_name)) ? $_old_membership_name[0] : '';
        $_new_membership_name =  (!empty($_new_membership_name)) ? $_new_membership_name[0] : '';
        $_old_subscription = (!empty($_old_subscription)) ? $_old_subscription[0] : '';
        $_new_subscription = (!empty($_new_subscription)) ? $_new_subscription[0] : '';
        $_plan =  (!empty($_plan)) ? $_plan[0] : '';
        $_migration_date =  (!empty($_migration_date)) ? $_migration_date[0] : '';
        $_user_id =  (!empty($_user_id)) ? $_user_id[0] : '';

        echo '<tr>';
        echo '<td> <a href="' . get_home_url() . '/wp-admin/user-edit.php?user_id=' . $_user_id . '" target="_blank">' . $_user_id . '</a></td>';
        echo '<td>' . $_old_membership_name . '</td>';
        echo '<td>' . $_new_membership_name . '</td>';
        echo '<td> <a href="' . get_home_url() . '/wp-admin/post.php?post=' . $_old_subscription . '&action=edit" target="_blank">' . $_old_subscription . '</a></td>';
        echo '<td> <a href="' . get_home_url() . '/wp-admin/post.php?post=' . $_new_subscription . '&action=edit" target="_blank">' . $_new_subscription . '</a></td>';
        echo '<td>' . $_plan . '</td>';
        echo '<td>' . $_migration_date . '</td>';

        echo '</tr>';
    }

    echo '</table>';

    // Pagination links
    echo '<div class="pagination" style="display: flex; justify-content: center; align-items: center; margin-top: 20px; padding: 10px;">';

    $current_page = isset($_GET['page_no']) ? intval($_GET['page_no']) : 1;

    $limit = 20; // Specify the maximum number of page numbers to display

    $total_items = $wpdb->get_var("SELECT COUNT(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = '_v2_migration_date'");
    $total_pages = ceil($total_items / $items_per_page);

    $displayed_pages = array();

    if ($total_pages > 1) {
        // Show previous arrow if not on the first page
        if ($current_page > 1) {
            echo '<a href="' . admin_url('admin.php?page=savage-membership-migration&page_no=' . ($current_page - 1)) . '" style="padding: 5px; margin-right: 5px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none;">&laquo; Prev</a>';
        }

        // Show first page if not already displayed
        if (!in_array(1, $displayed_pages)) {
            echo '<a href="' . admin_url('admin.php?page=savage-membership-migration&page_no=1') . '" style="padding: 5px; margin-right: 5px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none;">1</a>';

            if ($current_page > $limit) {
                echo '<span style="padding: 5px;">...</span>';
            }
        }

        // Show page numbers within the limit range
        $start = max(2, $current_page - floor($limit / 2));
        $end = min($start + $limit - 1, $total_pages - 1);

        for ($i = $start; $i <= $end; $i++) {
            if (!in_array($i, $displayed_pages)) {
                if ($i == $current_page) {
                    echo '<strong style="padding: 5px;">' . $i . '</strong>';
                } else {
                    echo '<a href="' . admin_url('admin.php?page=savage-membership-migration&page_no=' . $i) . '" style="padding: 5px; margin-right: 5px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none;">' . $i . '</a>';
                }
                $displayed_pages[] = $i;
            }
        }

        // Show last page if not already displayed
        if (!in_array($total_pages, $displayed_pages)) {
            if ($total_pages - $end > 1) {
                echo '<span style="padding: 5px;">...</span>';
            }
            echo '<a href="' . admin_url('admin.php?page=savage-membership-migration&page_no=' . $total_pages) . '" style="padding: 5px; margin-right: 5px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none;">' . $total_pages . '</a>';
        }

        // Show next arrow if not on the last page
        if ($current_page < $total_pages) {
            echo '<a href="' . admin_url('admin.php?page=savage-membership-migration&page_no=' . ($current_page + 1)) . '" style="padding: 5px; margin-right: 5px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none;">Next &raquo;</a>';
        }
    }

    echo '</div>';
} else {
    echo 'No record found.';
}


}