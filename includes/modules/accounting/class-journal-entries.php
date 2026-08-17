<?php
/**
 * Journal Entries - Double-Entry Accounting
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Journal_Entries
{
    private $entries_table;
    private $lines_table;

    public function __construct()
    {
        global $wpdb;
        $this->entries_table = $wpdb->prefix . 'erp_journal_entries';
        $this->lines_table = $wpdb->prefix . 'erp_journal_lines';

        add_action('wp_ajax_orerp_get_journal_entries', [$this, 'ajax_get_entries']);
        add_action('wp_ajax_orerp_save_journal_entry', [$this, 'ajax_save_entry']);
        add_action('wp_ajax_orerp_delete_journal_entry', [$this, 'ajax_delete_entry']);
        add_action('wp_ajax_orerp_post_journal_entry', [$this, 'ajax_post_entry']);
        add_action('wp_ajax_orerp_get_journal_entry', [$this, 'ajax_get_entry']);
        add_action('wp_ajax_orerp_get_financial_statements', [$this, 'ajax_get_financial_statements']);
    }

    public function render_page()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';

        if ($action === 'add' || $action === 'edit') {
            $this->render_form($action);
        } else {
            $this->render_list();
        }
    }

    private function render_list()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Journal Entries', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-journal&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="journal-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading journal entries...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_form($mode)
    {
        $entry = null;
        $lines = [];

        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $entry = $this->get_entry($id);
                $lines = $this->get_entry_lines($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Journal Entry', 'obydullah-restaurant-erp') : __('New Journal Entry', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-journal')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="journal-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_journal_entry">
                    <?php wp_nonce_field('orerp_save_journal_entry', 'journal_nonce'); ?>
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry->id ?? ''); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Entry Number', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" name="entry_number" class="regular-text" readonly
                                value="<?php echo esc_attr($entry->entry_number ?? Obydullah_ERP_Helpers::generate_entry_number()); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Date', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="date" name="date" class="regular-text" required
                                value="<?php echo esc_attr($entry->date ?? current_time('Y-m-d')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php esc_html_e('Description', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                        <textarea name="description" rows="2" class="large-text" required><?php echo esc_textarea($entry->description ?? ''); ?></textarea>
                    </div>

                    <h3><?php esc_html_e('Journal Lines', 'obydullah-restaurant-erp'); ?></h3>
                    <p class="description"><?php esc_html_e('Total Debit must equal Total Credit.', 'obydullah-restaurant-erp'); ?></p>

                    <table class="orerp-table widefat" id="journal-lines-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Account', 'obydullah-restaurant-erp'); ?></th>
                                <th width="150"><?php esc_html_e('Description', 'obydullah-restaurant-erp'); ?></th>
                                <th width="120"><?php esc_html_e('Debit', 'obydullah-restaurant-erp'); ?></th>
                                <th width="120"><?php esc_html_e('Credit', 'obydullah-restaurant-erp'); ?></th>
                                <th width="60"></th>
                            </tr>
                        </thead>
                        <tbody id="journal-lines-body">
                            <?php if (!empty($lines)): ?>
                            <?php foreach ($lines as $line): ?>
                            <tr class="journal-line-row">
                                <td>
                                    <select name="lines[<?php echo esc_attr($line->id); ?>][account_id]" class="line-account" required>
                                        <option value="<?php echo esc_attr($line->account_id); ?>"><?php echo esc_html($line->account_code . ' - ' . $line->account_name); ?></option>
                                    </select>
                                    <input type="hidden" name="lines[<?php echo esc_attr($line->id); ?>][id]" value="<?php echo esc_attr($line->id); ?>">
                                </td>
                                <td><input type="text" name="lines[<?php echo esc_attr($line->id); ?>][description]" class="line-desc" value="<?php echo esc_attr($line->description ?? ''); ?>"></td>
                                <td><input type="number" name="lines[<?php echo esc_attr($line->id); ?>][debit]" class="line-debit" step="0.01" min="0" value="<?php echo esc_attr($line->debit); ?>"></td>
                                <td><input type="number" name="lines[<?php echo esc_attr($line->id); ?>][credit]" class="line-credit" step="0.01" min="0" value="<?php echo esc_attr($line->credit); ?>"></td>
                                <td><button type="button" class="button remove-line">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">
                                    <button type="button" id="add-journal-line" class="button"><?php esc_html_e('+ Add Line', 'obydullah-restaurant-erp'); ?></button>
                                </td>
                                <td><strong><?php esc_html_e('Debit:', 'obydullah-restaurant-erp'); ?> <span id="total-debit">0.00</span></strong></td>
                                <td><strong><?php esc_html_e('Credit:', 'obydullah-restaurant-erp'); ?> <span id="total-credit">0.00</span></strong></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <span id="balance-indicator" class="status-badge pending"><?php esc_html_e('Out of Balance', 'obydullah-restaurant-erp'); ?></span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="submit" style="margin-top: 15px;">
                        <button type="submit" id="submit-journal" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Entry', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                        <?php if ($mode === 'edit' && isset($entry->is_posted) && !$entry->is_posted): ?>
                        <button type="button" id="post-journal" class="button button-secondary" data-id="<?php echo esc_attr($entry->id); ?>">
                            <?php esc_html_e('Post Entry', 'obydullah-restaurant-erp'); ?>
                        </button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function get_entries($args = [])
    {
        global $wpdb;

        $defaults = ['per_page' => 20, 'page' => 1, 'date_from' => '', 'date_to' => '', 'posted' => ''];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['date_from'])) {
            $where .= ' AND date >= %s';
            $prepare_args[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where .= ' AND date <= %s';
            $prepare_args[] = $args['date_to'];
        }

        if ($args['posted'] !== '') {
            $where .= ' AND is_posted = %d';
            $prepare_args[] = intval($args['posted']);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        $count_query = "SELECT COUNT(*) FROM {$this->entries_table} WHERE {$where}";
        if (!empty($prepare_args)) {
            $count_query = $wpdb->prepare($count_query, $prepare_args);
        }
        $total = intval($wpdb->get_var($count_query));

        $query = "SELECT * FROM {$this->entries_table} WHERE {$where} ORDER BY date DESC, id DESC LIMIT %d OFFSET %d";
        $query_args = array_merge($prepare_args, [$args['per_page'], $offset]);
        $results = $wpdb->get_results($wpdb->prepare($query, $query_args));

        $helpers = new Obydullah_ERP_Helpers();
        foreach ($results as &$row) {
            $row->formatted_date = $helpers->format_date($row->date);
            $row->totals = $this->get_entry_totals($row->id);
        }

        return [
            'entries'      => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function get_entry($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->entries_table} WHERE id = %d", intval($id)));
    }

    public function get_entry_lines($entry_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT jl.*, ja.code as account_code, ja.name as account_name
            FROM {$this->lines_table} jl
            LEFT JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
            WHERE jl.entry_id = %d
            ORDER BY jl.id",
            intval($entry_id)
        )) ?: [];
    }

    public function get_entry_totals($entry_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit
            FROM {$this->lines_table} WHERE entry_id = %d",
            intval($entry_id)
        ));
    }

    public function create_entry($data)
    {
        global $wpdb;

        $entry_number = sanitize_text_field($data['entry_number'] ?? Obydullah_ERP_Helpers::generate_entry_number());
        $date = sanitize_text_field($data['date'] ?? current_time('Y-m-d'));
        $description = sanitize_textarea_field($data['description'] ?? '');
        $reference_type = sanitize_text_field($data['reference_type'] ?? '');
        $reference_id = intval($data['reference_id'] ?? 0);
        $lines = $data['lines'] ?? [];

        if (empty($description) || empty($lines)) {
            return new WP_Error('missing_fields', __('Description and lines are required.', 'obydullah-restaurant-erp'));
        }

        $total_debit = 0;
        $total_credit = 0;

        foreach ($lines as $line) {
            $total_debit += floatval($line['debit'] ?? 0);
            $total_credit += floatval($line['credit'] ?? 0);
        }

        if (abs($total_debit - $total_credit) > 0.01) {
            return new WP_Error('unbalanced', __('Total debit must equal total credit.', 'obydullah-restaurant-erp'));
        }

        $wpdb->insert($this->entries_table, [
            'entry_number'   => $entry_number,
            'date'           => $date,
            'description'    => $description,
            'reference_type' => $reference_type,
            'reference_id'   => $reference_id,
            'is_posted'      => 1,
            'created_by'     => get_current_user_id(),
        ]);

        $entry_id = $wpdb->insert_id;

        if (!$entry_id) {
            return new WP_Error('create_failed', __('Failed to create journal entry.', 'obydullah-restaurant-erp'));
        }

        foreach ($lines as $line) {
            $account_id = intval($line['account_id'] ?? 0);
            $account_code = sanitize_text_field($line['account_code'] ?? '');

            if (!$account_id && !empty($account_code)) {
                $account = Obydullah_ERP_Helpers::get_account_id_by_code($account_code);
                $account_id = $account;
            }

            if ($account_id) {
                $wpdb->insert($this->lines_table, [
                    'entry_id'    => $entry_id,
                    'account_id'  => $account_id,
                    'debit'       => floatval($line['debit'] ?? 0),
                    'credit'      => floatval($line['credit'] ?? 0),
                    'description' => sanitize_text_field($line['description'] ?? ''),
                ]);
            }
        }

        return $entry_id;
    }

    public function save_entry($data)
    {
        global $wpdb;

        $id = intval($data['entry_id'] ?? 0);
        $entry_number = sanitize_text_field($data['entry_number'] ?? Obydullah_ERP_Helpers::generate_entry_number());
        $date = sanitize_text_field($data['date'] ?? '');
        $description = sanitize_textarea_field($data['description'] ?? '');
        $lines = $data['lines'] ?? [];

        if (empty($date) || empty($description)) {
            return new WP_Error('missing_fields', __('Date and description are required.', 'obydullah-restaurant-erp'));
        }

        if ($id > 0) {
            $existing = $this->get_entry($id);
            if ($existing && $existing->is_posted) {
                return new WP_Error('already_posted', __('Cannot edit posted entries.', 'obydullah-restaurant-erp'));
            }
        }

        $total_debit = 0;
        $total_credit = 0;

        foreach ($lines as $line) {
            $total_debit += floatval($line['debit'] ?? 0);
            $total_credit += floatval($line['credit'] ?? 0);
        }

        if (abs($total_debit - $total_credit) > 0.01) {
            return new WP_Error('unbalanced', __('Total debit must equal total credit.', 'obydullah-restaurant-erp'));
        }

        if ($id > 0) {
            $wpdb->update($this->entries_table, [
                'entry_number' => $entry_number,
                'date'         => $date,
                'description'  => $description,
            ], ['id' => $id]);
            $wpdb->delete($this->lines_table, ['entry_id' => $id]);
        } else {
            $wpdb->insert($this->entries_table, [
                'entry_number' => $entry_number,
                'date'         => $date,
                'description'  => $description,
                'is_posted'    => 0,
                'created_by'   => get_current_user_id(),
            ]);
            $id = $wpdb->insert_id;
        }

        foreach ($lines as $line) {
            $account_id = intval($line['account_id'] ?? 0);
            $account_code = sanitize_text_field($line['account_code'] ?? '');

            if (!$account_id && !empty($account_code)) {
                $account = Obydullah_ERP_Helpers::get_account_id_by_code($account_code);
                $account_id = $account;
            }

            if ($account_id) {
                $wpdb->insert($this->lines_table, [
                    'entry_id'    => $id,
                    'account_id'  => $account_id,
                    'debit'       => floatval($line['debit'] ?? 0),
                    'credit'      => floatval($line['credit'] ?? 0),
                    'description' => sanitize_text_field($line['description'] ?? ''),
                ]);
            }
        }

        return $id;
    }

    public function post_entry($id)
    {
        global $wpdb;

        $id = intval($id);
        $entry = $this->get_entry($id);

        if (!$entry) {
            return new WP_Error('not_found', __('Entry not found.', 'obydullah-restaurant-erp'));
        }

        if ($entry->is_posted) {
            return new WP_Error('already_posted', __('Entry is already posted.', 'obydullah-restaurant-erp'));
        }

        $totals = $this->get_entry_totals($id);
        if (abs($totals->total_debit - $totals->total_credit) > 0.01) {
            return new WP_Error('unbalanced', __('Cannot post unbalanced entry.', 'obydullah-restaurant-erp'));
        }

        $wpdb->update($this->entries_table, ['is_posted' => 1], ['id' => $id]);
        return true;
    }

    public function delete_entry($id)
    {
        global $wpdb;
        $id = intval($id);

        $entry = $this->get_entry($id);
        if ($entry && $entry->is_posted) {
            return new WP_Error('already_posted', __('Cannot delete posted entries.', 'obydullah-restaurant-erp'));
        }

        $wpdb->delete($this->lines_table, ['entry_id' => $id]);
        $wpdb->delete($this->entries_table, ['id' => $id]);
        return true;
    }

    public function get_profit_loss($from = '', $to = '')
    {
        $financial = new Obydullah_ERP_Financial_Reports();
        return $financial->get_profit_loss($from, $to);
    }

    public function get_balance_sheet($as_of = '')
    {
        $financial = new Obydullah_ERP_Financial_Reports();
        return $financial->get_balance_sheet($as_of);
    }

    // --- AJAX ---

    public function ajax_get_entries()
    {
        check_ajax_referer('orerp_journal', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->get_entries([
            'per_page'  => intval($_GET['per_page'] ?? 20),
            'page'      => intval($_GET['page'] ?? 1),
            'date_from' => sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')),
            'date_to'   => sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')),
        ]);

        wp_send_json_success($result);
    }

    public function ajax_save_entry()
    {
        check_ajax_referer('orerp_save_journal_entry', 'journal_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_entry($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Journal entry saved.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_entry()
    {
        check_ajax_referer('orerp_journal', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $result = $this->delete_entry($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Entry deleted.', 'obydullah-restaurant-erp'));
    }

    public function ajax_post_entry()
    {
        check_ajax_referer('orerp_journal', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['entry_id'] ?? 0);
        $result = $this->post_entry($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Entry posted.', 'obydullah-restaurant-erp'));
    }

    public function ajax_get_entry()
    {
        check_ajax_referer('orerp_journal', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_GET['id'] ?? 0);
        $entry = $this->get_entry($id);

        if (!$entry) {
            wp_send_json_error(__('Entry not found.', 'obydullah-restaurant-erp'));
        }

        $entry->lines = $this->get_entry_lines($id);
        wp_send_json_success($entry);
    }

    public function ajax_get_financial_statements()
    {
        check_ajax_referer('orerp_journal', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $type = sanitize_text_field(wp_unslash($_GET['type'] ?? 'pl'));
        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to   = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));

        if ($type === 'pl') {
            wp_send_json_success($this->get_profit_loss($from, $to));
        } else {
            wp_send_json_success($this->get_balance_sheet($to ?: current_time('Y-m-d')));
        }
    }
}
