<?php
/**
 * Employee Attendance & Shifts
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Attendance
{
    private $attendance_table;
    private $shifts_table;
    private $employees_table;

    public function __construct()
    {
        global $wpdb;
        $this->attendance_table = $wpdb->prefix . 'erp_attendance';
        $this->shifts_table = $wpdb->prefix . 'erp_shifts';
        $this->employees_table = $wpdb->prefix . 'erp_employees';

        add_action('wp_ajax_orerp_clock_in', [$this, 'orerp_ajax_clock_in']);
        add_action('wp_ajax_orerp_clock_out', [$this, 'orerp_ajax_clock_out']);
        add_action('wp_ajax_orerp_get_attendance', [$this, 'orerp_ajax_get_attendance']);
        add_action('wp_ajax_orerp_save_attendance', [$this, 'orerp_ajax_save_attendance']);
        add_action('wp_ajax_orerp_delete_attendance', [$this, 'orerp_ajax_delete_attendance']);

        add_action('wp_ajax_orerp_get_shifts', [$this, 'orerp_ajax_get_shifts']);
        add_action('wp_ajax_orerp_save_shift', [$this, 'orerp_ajax_save_shift']);
        add_action('wp_ajax_orerp_delete_shift', [$this, 'orerp_ajax_delete_shift']);
    }

    public function orerp_render_page()
    {
        global $wpdb;

        $employees = $wpdb->get_results("SELECT id, employee_code, user_id FROM {$this->employees_table} WHERE is_active = 1 ORDER BY employee_code") ?: [];
        $branches = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}erp_branches WHERE is_active = 1 ORDER BY name") ?: [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Attendance & Shifts', 'obydullah-restaurant-erp'); ?></h1>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Attendance Log', 'obydullah-restaurant-erp'); ?></h2>
                </div>

                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('Employee', 'obydullah-restaurant-erp'); ?></label>
                        <select id="attendance-employee-filter">
                            <option value=""><?php esc_html_e('All Employees', 'obydullah-restaurant-erp'); ?></option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo esc_attr($emp->id); ?>"><?php echo esc_html($emp->employee_code); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
                        <select id="attendance-branch-filter">
                            <option value=""><?php esc_html_e('All Branches', 'obydullah-restaurant-erp'); ?></option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo esc_attr($branch->id); ?>"><?php echo esc_html($branch->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('From', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="attendance-date-from" class="regular-text" value="<?php echo esc_attr(gmdate('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="attendance-date-to" class="regular-text" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="run-attendance" class="button button-primary">
                            <?php esc_html_e('Load Attendance', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </div>
                </div>

                <div id="attendance-list">
                    <p class="description"><?php esc_html_e('Click "Load Attendance" to view records.', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>

            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Shifts', 'obydullah-restaurant-erp'); ?></h2>
                </div>

                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
                        <select id="shift-branch-filter">
                            <option value=""><?php esc_html_e('All Branches', 'obydullah-restaurant-erp'); ?></option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo esc_attr($branch->id); ?>"><?php echo esc_html($branch->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="run-shifts" class="button button-primary">
                            <?php esc_html_e('Load Shifts', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </div>
                </div>

                <div id="shifts-list">
                    <p class="description"><?php esc_html_e('Click "Load Shifts" to view available shifts.', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function orerp_clock_in($employee_id, $branch_id)
    {
        global $wpdb;

        $today = current_time('Y-m-d');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->attendance_table}
            WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL",
            $employee_id,
            $today
        ));

        if ($existing) {
            return new WP_Error('already_clocked_in', __('Employee is already clocked in.', 'obydullah-restaurant-erp'));
        }

        $wpdb->insert($this->attendance_table, [
            'employee_id' => $employee_id,
            'branch_id'   => $branch_id,
            'orerp_clock_in'    => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    public function orerp_clock_out($employee_id, $notes = 'orerp_')
    {
        global $wpdb;

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->attendance_table}
            WHERE employee_id = %d AND clock_out IS NULL
            ORDER BY clock_in DESC LIMIT 1",
            $employee_id
        ));

        if (!$record) {
            return new WP_Error('not_clocked_in', __('Employee is not clocked in.', 'obydullah-restaurant-erp'));
        }

        $wpdb->update($this->attendance_table, [
            'orerp_clock_out' => current_time('mysql'),
            'notes'     => $notes,
        ], ['id' => $record->id]);

        return $record->id;
    }

    public function orerp_get_attendance($args = [])
    {
        global $wpdb;

        $defaults = [
            'per_page'   => 20,
            'page'       => 1,
            'employee_id' => 0,
            'branch_id'  => 0,
            'date_from'  => 'orerp_',
            'date_to'    => 'orerp_',
        ];

        $args = wp_parse_args($args, $defaults);
        $where = '1=1';
        $prepare_args = [];

        if ($args['employee_id'] > 0) {
            $where .= ' AND a.employee_id = %d';
            $prepare_args[] = $args['employee_id'];
        }

        if ($args['branch_id'] > 0) {
            $where .= ' AND a.branch_id = %d';
            $prepare_args[] = $args['branch_id'];
        }

        if (!empty($args['date_from'])) {
            $where .= ' AND DATE(a.clock_in) >= %s';
            $prepare_args[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where .= ' AND DATE(a.clock_in) <= %s';
            $prepare_args[] = $args['date_to'];
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        if (!empty($prepare_args)) {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->attendance_table} a WHERE {$where}", $prepare_args)));
        } else {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->attendance_table} a WHERE 1 = %d", 1)));
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, e.employee_code, e.position, b.name as branch_name
            FROM {$this->attendance_table} a
            LEFT JOIN {$this->employees_table} e ON a.employee_id = e.id
            LEFT JOIN {$wpdb->prefix}erp_branches b ON a.branch_id = b.id
            WHERE {$where}
            ORDER BY a.clock_in DESC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        $helpers = new Obydullah_ERP_Helpers();
        if ($results) {
            foreach ($results as &$row) {
                $row->formatted_clock_in = $helpers->orerp_format_date($row->clock_in);
                $row->formatted_clock_out = $row->clock_out ? $helpers->orerp_format_date($row->clock_out) : '-';
                $row->hours_worked = $this->orerp_calculate_hours($row->clock_in, $row->clock_out);
            }
        }

        return [
            'attendance'  => $results ?: [],
            'total'       => $total,
            'total_pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function orerp_save_attendance($data)
    {
        global $wpdb;

        $id = intval($data['attendance_id'] ?? 0);
        $employee_id = intval($data['employee_id'] ?? 0);
        $branch_id = intval($data['branch_id'] ?? 0);
        $clock_in = sanitize_text_field($data['orerp_clock_in'] ?? 'orerp_');
        $clock_out = sanitize_text_field($data['orerp_clock_out'] ?? 'orerp_');
        $notes = sanitize_textarea_field($data['notes'] ?? 'orerp_');

        if (!$employee_id || !$branch_id || empty($clock_in)) {
            return new WP_Error('missing_fields', __('Employee, branch, and clock-in time are required.', 'obydullah-restaurant-erp'));
        }

        $save_data = [
            'employee_id' => $employee_id,
            'branch_id'   => $branch_id,
            'orerp_clock_in'    => $clock_in,
            'orerp_clock_out'   => $clock_out ?: null,
            'notes'       => $notes,
        ];

        if ($id > 0) {
            $result = $wpdb->update($this->attendance_table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->attendance_table, $save_data);
            $id = $wpdb->insert_id;
        }

        return $result !== false ? $id : new WP_Error('save_failed', __('Failed to save attendance.', 'obydullah-restaurant-erp'));
    }

    public function orerp_delete_attendance($id)
    {
        global $wpdb;
        return $wpdb->delete($this->attendance_table, ['id' => intval($id)]) !== false;
    }

    private function orerp_calculate_hours($clock_in, $clock_out)
    {
        if (empty($clock_out)) {
            return '-';
        }

        $start = strtotime($clock_in);
        $end = strtotime($clock_out);
        $hours = round(($end - $start) / 3600, 2);

        return number_format($hours, 2) . 'h';
    }

    // --- Shifts ---

    public function orerp_get_shifts($branch_id = 0)
    {
        global $wpdb;

        $where = '1=1';
        $prepare_args = [];

        if ($branch_id > 0) {
            $where .= ' AND s.branch_id = %d';
            $prepare_args[] = $branch_id;
        }

        if (!empty($prepare_args)) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, b.name AS branch_name
                FROM {$this->shifts_table} s
                LEFT JOIN {$wpdb->prefix}erp_branches b ON s.branch_id = b.id
                WHERE {$where} ORDER BY s.start_time",
                $prepare_args
            )) ?: [];
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, b.name AS branch_name
                FROM {$this->shifts_table} s
                LEFT JOIN {$wpdb->prefix}erp_branches b ON s.branch_id = b.id
                WHERE 1 = %d ORDER BY s.start_time",
                1
            )) ?: [];
        }
    }

    public function orerp_save_shift($data)
    {
        global $wpdb;

        $id = intval($data['shift_id'] ?? 0);
        $branch_id = intval($data['branch_id'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? 'orerp_');
        $start_time = sanitize_text_field($data['start_time'] ?? 'orerp_');
        $end_time = sanitize_text_field($data['end_time'] ?? 'orerp_');

        if (!$branch_id || empty($name) || empty($start_time) || empty($end_time)) {
            return new WP_Error('missing_fields', __('Branch, name, start and end time are required.', 'obydullah-restaurant-erp'));
        }

        $save_data = [
            'branch_id'  => $branch_id,
            'name'       => $name,
            'start_time' => $start_time,
            'end_time'   => $end_time,
            'is_active'  => 1,
        ];

        if ($id > 0) {
            $result = $wpdb->update($this->shifts_table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->shifts_table, $save_data);
            $id = $wpdb->insert_id;
        }

        return $result !== false ? $id : new WP_Error('save_failed', __('Failed to save shift.', 'obydullah-restaurant-erp'));
    }

    public function orerp_delete_shift($id)
    {
        global $wpdb;
        return $wpdb->delete($this->shifts_table, ['id' => intval($id)]) !== false;
    }

    // --- AJAX ---

    public function orerp_ajax_clock_in()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $employee_id = intval($_POST['employee_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);

        $result = $this->orerp_clock_in($employee_id, $branch_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Clocked in successfully.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_clock_out()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $employee_id = intval($_POST['employee_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? 'orerp_');

        $result = $this->orerp_clock_out($employee_id, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Clocked out successfully.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_get_attendance()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_get_attendance([
            'per_page'    => intval($_GET['per_page'] ?? 20),
            'page'        => intval($_GET['page'] ?? 1),
            'employee_id' => intval($_GET['employee_id'] ?? 0),
            'branch_id'   => intval($_GET['branch_id'] ?? 0),
            'date_from'   => sanitize_text_field(wp_unslash($_GET['date_from'] ?? 'orerp_')),
            'date_to'     => sanitize_text_field(wp_unslash($_GET['date_to'] ?? 'orerp_')),
        ]);

        wp_send_json_success($result);
    }

    public function orerp_ajax_save_attendance()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_save_attendance($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Attendance saved.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_delete_attendance()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->orerp_delete_attendance($id);
        wp_send_json_success(__('Attendance deleted.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_get_shifts()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $branch_id = intval($_GET['branch_id'] ?? 0);
        wp_send_json_success($this->orerp_get_shifts($branch_id));
    }

    public function orerp_ajax_save_shift()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_save_shift($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Shift saved.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_delete_shift()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->orerp_delete_shift($id);
        wp_send_json_success(__('Shift deleted.', 'obydullah-restaurant-erp'));
    }
}
