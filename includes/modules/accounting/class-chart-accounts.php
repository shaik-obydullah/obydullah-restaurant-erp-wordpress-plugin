<?php
/**
 * Chart of Accounts
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Chart_Accounts
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_accounts';

        add_action('wp_ajax_orerp_get_accounts', [$this, 'ajax_get_accounts']);
        add_action('wp_ajax_orerp_save_account', [$this, 'ajax_save_account']);
        add_action('wp_ajax_orerp_delete_account', [$this, 'ajax_delete_account']);
        add_action('wp_ajax_orerp_get_account_balances', [$this, 'ajax_get_account_balances']);
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
            <h1 class="wp-heading-inline"><?php esc_html_e('Chart of Accounts', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-accounting&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="accounts-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading accounts...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Trial Balance Card -->
            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Trial Balance', 'obydullah-restaurant-erp'); ?></h2>
                    <button type="button" id="refresh-trial-balance" class="orerp-btn orerp-btn-sm orerp-btn-outline">
                        <?php esc_html_e('Refresh', 'obydullah-restaurant-erp'); ?>
                    </button>
                </div>
                <div id="trial-balance-content">
                    <p class="description"><?php esc_html_e('Click refresh to view current balances.', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_form($mode)
    {
        $account = null;
        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $account = $this->get_account($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Account', 'obydullah-restaurant-erp') : __('Add New Account', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-accounting')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="account-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_account">
                    <?php wp_nonce_field('orerp_save_account', 'account_nonce'); ?>
                    <input type="hidden" name="account_id" value="<?php echo esc_attr($account->id ?? ''); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Account Code', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="code" class="regular-text" required
                                value="<?php echo esc_attr($account->code ?? ''); ?>"
                                placeholder="<?php esc_attr_e('e.g. 1000', 'obydullah-restaurant-erp'); ?>">
                            <p class="description"><?php esc_html_e('Unique numeric code', 'obydullah-restaurant-erp'); ?></p>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Account Name', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="name" class="regular-text" required
                                value="<?php echo esc_attr($account->name ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Account Type', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select name="type" class="regular-text" required>
                                <option value="asset" <?php selected($account->type ?? '', 'asset'); ?>><?php esc_html_e('Asset', 'obydullah-restaurant-erp'); ?></option>
                                <option value="liability" <?php selected($account->type ?? '', 'liability'); ?>><?php esc_html_e('Liability', 'obydullah-restaurant-erp'); ?></option>
                                <option value="equity" <?php selected($account->type ?? '', 'equity'); ?>><?php esc_html_e('Equity', 'obydullah-restaurant-erp'); ?></option>
                                <option value="revenue" <?php selected($account->type ?? '', 'revenue'); ?>><?php esc_html_e('Revenue', 'obydullah-restaurant-erp'); ?></option>
                                <option value="expense" <?php selected($account->type ?? '', 'expense'); ?>><?php esc_html_e('Expense', 'obydullah-restaurant-erp'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Parent Account', 'obydullah-restaurant-erp'); ?></label>
                            <select name="parent_id" class="regular-text">
                                <option value="0"><?php esc_html_e('None (Top Level)', 'obydullah-restaurant-erp'); ?></option>
                                <?php $this->render_parent_options($account->parent_id ?? 0, $account->id ?? 0); ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php esc_html_e('Description', 'obydullah-restaurant-erp'); ?></label>
                        <textarea name="description" rows="3" class="large-text"><?php echo esc_textarea($account->description ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked($account->is_active ?? 1, 1); ?>>
                            <?php esc_html_e('Active', 'obydullah-restaurant-erp'); ?>
                        </label>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-account" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Account', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_parent_options($selected = 0, $exclude = 0)
    {
        global $wpdb;
        $accounts = $wpdb->get_results(
            "SELECT id, code, name FROM {$this->table} WHERE is_active = 1 AND id != %d ORDER BY code",
            intval($exclude)
        );

        foreach ($accounts as $acc) {
            printf(
                '<option value="%d" %s>%s - %s</option>',
                intval($acc->id),
                selected($selected, $acc->id, false),
                esc_html($acc->code),
                esc_html($acc->name)
            );
        }
    }

    public function get_accounts($args = [])
    {
        global $wpdb;

        $defaults = ['per_page' => 0, 'type' => '', 'active' => '', 'orderby' => 'code', 'order' => 'ASC'];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['type'])) {
            $where .= ' AND type = %s';
            $prepare_args[] = $args['type'];
        }

        if ($args['active'] !== '') {
            $where .= ' AND is_active = %d';
            $prepare_args[] = intval($args['active']);
        }

        if ($args['per_page'] > 0) {
            $offset = (($args['page'] ?? 1) - 1) * $args['per_page'];
            if (!empty($prepare_args)) {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE {$where} ORDER BY code ASC LIMIT " . intval($args['per_page']) . " OFFSET " . intval($offset), $prepare_args));
            } else {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE 1 = %d ORDER BY code ASC LIMIT " . intval($args['per_page']) . " OFFSET " . intval($offset), 1));
            }
        } else {
            if (!empty($prepare_args)) {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE {$where} ORDER BY code ASC", $prepare_args));
            } else {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE 1 = %d ORDER BY code ASC", 1));
            }
        }

        return $results ?: [];
    }

    public function get_account($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", intval($id)));
    }

    public function get_account_by_code($code)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE code = %s", $code));
    }

    public function save_account($data)
    {
        global $wpdb;

        $id = intval($data['account_id'] ?? 0);
        $code = sanitize_text_field($data['code'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $type = sanitize_text_field($data['type'] ?? '');
        $parent_id = intval($data['parent_id'] ?? 0);
        $description = sanitize_textarea_field($data['description'] ?? '');
        $is_active = isset($data['is_active']) ? 1 : 0;

        if (empty($code) || empty($name) || empty($type)) {
            return new WP_Error('missing_fields', __('Code, name, and type are required.', 'obydullah-restaurant-erp'));
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE code = %s AND id != %d",
            $code, $id
        ));
        if ($existing) {
            return new WP_Error('duplicate_code', __('Account code already exists.', 'obydullah-restaurant-erp'));
        }

        $save_data = compact('code', 'name', 'type', 'parent_id', 'description', 'is_active');

        if ($id > 0) {
            $result = $wpdb->update($this->table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->table, $save_data);
            $id = $wpdb->insert_id;
        }

        return $result !== false ? $id : new WP_Error('save_failed', __('Failed to save account.', 'obydullah-restaurant-erp'));
    }

    public function delete_account($id)
    {
        global $wpdb;

        $lines_table = $wpdb->prefix . 'erp_journal_lines';
        $has_entries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$lines_table} WHERE account_id = %d",
            intval($id)
        ));

        if ($has_entries > 0) {
            return new WP_Error('has_entries', __('Cannot delete account with journal entries.', 'obydullah-restaurant-erp'));
        }

        $wpdb->delete($this->table, ['id' => intval($id)]);
        return true;
    }

    public function get_account_balance($account_id)
    {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(jl.debit), 0) as total_debit, COALESCE(SUM(jl.credit), 0) as total_credit
            FROM {$wpdb->prefix}erp_journal_lines jl
            JOIN {$wpdb->prefix}erp_journal_entries je ON jl.entry_id = je.id
            WHERE jl.account_id = %d AND je.is_posted = 1",
            intval($account_id)
        ));

        $debit = floatval($result->total_debit);
        $credit = floatval($result->total_credit);

        return ['debit' => $debit, 'credit' => $credit, 'balance' => $debit - $credit];
    }

    public function get_trial_balance()
    {
        global $wpdb;

        $accounts = $this->get_accounts(['active' => 1]);
        $trial = [];
        $total_debit = 0;
        $total_credit = 0;

        foreach ($accounts as $account) {
            $balance = $this->get_account_balance($account->id);

            if ($balance['debit'] == 0 && $balance['credit'] == 0) {
                continue;
            }

            $trial[] = [
                'code'     => $account->code,
                'name'     => $account->name,
                'type'     => $account->type,
                'debit'    => $balance['debit'],
                'credit'   => $balance['credit'],
                'balance'  => $balance['balance'],
            ];

            $total_debit += $balance['debit'];
            $total_credit += $balance['credit'];
        }

        return [
            'accounts'     => $trial,
            'total_debit'  => $total_debit,
            'total_credit' => $total_credit,
            'is_balanced'  => abs($total_debit - $total_credit) < 0.01,
        ];
    }

    public function get_account_balances_by_type()
    {
        global $wpdb;

        $types = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        $result = [];

        foreach ($types as $type) {
            $accounts = $this->get_accounts(['type' => $type, 'active' => 1]);
            $total = 0;

            foreach ($accounts as $account) {
                $balance = $this->get_account_balance($account->id);
                $total += $balance['balance'];
            }

            $result[$type] = $total;
        }

        return $result;
    }

    // --- AJAX ---

    public function ajax_get_accounts()
    {
        check_ajax_referer('orerp_accounting', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $accounts = $this->get_accounts(['active' => isset($_GET['all']) ? '' : 1]);

        $helpers = new Obydullah_ERP_Helpers();
        foreach ($accounts as &$acc) {
            $balance = $this->get_account_balance($acc->id);
            $acc->formatted_balance = Obydullah_ERP_Helpers::format_currency(abs($balance['balance']));
            $acc->balance_direction = $balance['balance'] >= 0 ? 'debit' : 'credit';
        }

        wp_send_json_success($accounts);
    }

    public function ajax_save_account()
    {
        check_ajax_referer('orerp_save_account', 'account_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_account($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Account saved.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_account()
    {
        check_ajax_referer('orerp_accounting', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $result = $this->delete_account($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Account deleted.', 'obydullah-restaurant-erp'));
    }

    public function ajax_get_account_balances()
    {
        check_ajax_referer('orerp_accounting', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($this->get_trial_balance());
    }
}
