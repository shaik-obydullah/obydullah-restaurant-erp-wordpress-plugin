<?php
/**
 * Recipe Management
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Recipes
{
    private $table_recipes;
    private $table_ingredients;

    public function __construct()
    {
        global $wpdb;
        $this->table_recipes     = $wpdb->prefix . 'erp_recipes';
        $this->table_ingredients = $wpdb->prefix . 'erp_recipe_ingredients';

        add_action('wp_ajax_orerp_get_recipes', [$this, 'orerp_ajax_get_recipes']);
        add_action('wp_ajax_orerp_save_recipe', [$this, 'orerp_ajax_save_recipe']);
        add_action('wp_ajax_orerp_delete_recipe', [$this, 'orerp_ajax_delete_recipe']);
        add_action('wp_ajax_orerp_get_recipe', [$this, 'orerp_ajax_get_recipe']);
    }

    public function orerp_render_page()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';

        if ($action === 'add' || $action === 'edit') {
            $this->orerp_render_form($action);
        } else {
            $this->orerp_render_list();
        }
    }

    private function orerp_render_list()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Recipes', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-recipes&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New Recipe', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="recipes-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading recipes...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function orerp_render_form($mode)
    {
        $recipe = null;
        $ingredients = [];

        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $recipe = $this->orerp_get_recipe($id);
                $ingredients = $this->orerp_get_ingredients($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Recipe', 'obydullah-restaurant-erp') : __('Add New Recipe', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-recipes')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="recipe-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_recipe">
                    <?php wp_nonce_field('orerp_recipes', 'recipe_nonce'); ?>
                    <input type="hidden" name="recipe_id" value="<?php echo esc_attr($recipe->id ?? 'orerp_'); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Recipe Name', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="name" class="regular-text" required
                                value="<?php echo esc_attr($recipe->name ?? 'orerp_'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Linked Product', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select name="product_id" class="regular-text" required>
                                <option value=""><?php esc_html_e('Select Product', 'obydullah-restaurant-erp'); ?></option>
                                <?php $this->orerp_render_product_options($recipe->product_id ?? 0); ?>
                            </select>
                            <p class="description"><?php esc_html_e('WooCommerce product this recipe produces', 'obydullah-restaurant-erp'); ?></p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Servings', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" name="servings" class="small-text" min="1"
                                value="<?php echo esc_attr($recipe->servings ?? 1); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Prep Time (min)', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" name="prep_time_minutes" class="small-text" min="0"
                                value="<?php echo esc_attr($recipe->prep_time_minutes ?? 'orerp_'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Cook Time (min)', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" name="cook_time_minutes" class="small-text" min="0"
                                value="<?php echo esc_attr($recipe->cook_time_minutes ?? 'orerp_'); ?>">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?php checked($recipe->is_active ?? 1, 1); ?>>
                                <?php esc_html_e('Active', 'obydullah-restaurant-erp'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php esc_html_e('Instructions', 'obydullah-restaurant-erp'); ?></label>
                        <textarea name="instructions" rows="4" class="large-text"><?php echo esc_textarea($recipe->instructions ?? 'orerp_'); ?></textarea>
                    </div>

                    <!-- Ingredients Section -->
                    <h3><?php esc_html_e('Ingredients', 'obydullah-restaurant-erp'); ?></h3>
                    <table class="orerp-table widefat" id="ingredients-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product/Ingredient', 'obydullah-restaurant-erp'); ?></th>
                                <th><?php esc_html_e('Quantity', 'obydullah-restaurant-erp'); ?></th>
                                <th><?php esc_html_e('Unit', 'obydullah-restaurant-erp'); ?></th>
                                <th><?php esc_html_e('Notes', 'obydullah-restaurant-erp'); ?></th>
                                <th class="text-right"><?php esc_html_e('Actions', 'obydullah-restaurant-erp'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ingredients-body">
                            <?php if (!empty($ingredients)) : ?>
                                <?php foreach ($ingredients as $ing) : ?>
                                    <tr class="ingredient-row">
                                        <td>
                                            <select name="ingredients[][product_id]" class="regular-text" required>
                                                <option value=""><?php esc_html_e('Select', 'obydullah-restaurant-erp'); ?></option>
                                                <?php $this->orerp_render_product_options($ing->product_id); ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="ingredients[][quantity]" class="small-text" step="0.001" min="0" required value="<?php echo esc_attr($ing->quantity); ?>"></td>
                                        <td><input type="text" name="ingredients[][unit]" class="regular-text" value="<?php echo esc_attr($ing->unit); ?>" placeholder="<?php esc_attr_e('g, kg, ml, L...', 'obydullah-restaurant-erp'); ?>"></td>
                                        <td><input type="text" name="ingredients[][notes]" class="regular-text" value="<?php echo esc_attr($ing->notes); ?>"></td>
                                        <td class="text-right"><button type="button" class="button remove-ingredient"><?php esc_html_e('Remove', 'obydullah-restaurant-erp'); ?></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" id="add-ingredient" class="button">
                            <?php esc_html_e('+ Add Ingredient', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </p>

                    <p class="submit">
                        <button type="submit" id="submit-recipe" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Recipe', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function orerp_render_product_options($selected = 0)
    {
        if (!class_exists('WooCommerce')) {
            echo '<option value="0">WooCommerce not active</option>';
            return;
        }

        $products = wc_get_products(['limit' => 500, 'status' => 'publish', 'orderby' => 'name', 'order' => 'ASC']);

        foreach ($products as $product) {
            printf(
                '<option value="%d" %s>%s</option>',
                intval($product->get_id()),
                selected($selected, $product->get_id(), false),
                esc_html($product->get_name())
            );
        }
    }

    public function orerp_get_recipes($args = [])
    {
        global $wpdb;

        $defaults = ['per_page' => 20, 'page' => 1, 'active' => 'orerp_', 'search' => 'orerp_'];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if ($args['active'] !== 'orerp_') {
            $where .= ' AND r.is_active = %d';
            $prepare_args[] = intval($args['active']);
        }

        if (!empty($args['search'])) {
            $where .= ' AND r.name LIKE %s';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_recipes} r WHERE {$where}",
            $prepare_args
        ));

        $offset = ($args['page'] - 1) * $args['per_page'];
        $recipes = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title AS product_name
            FROM {$this->table_recipes} r
            LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID
            WHERE {$where}
            ORDER BY r.name ASC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        )) ?: [];

        foreach ($recipes as &$recipe) {
            $recipe->ingredient_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_ingredients} WHERE recipe_id = %d",
                $recipe->id
            ));
            $recipe->total_time = ($recipe->prep_time_minutes ?? 0) + ($recipe->cook_time_minutes ?? 0);
        }

        return [
            'recipes'       => $recipes,
            'total'         => $total,
            'total_pages'   => ceil($total / $args['per_page']),
            'current_page'  => $args['page'],
        ];
    }

    public function orerp_get_recipe($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_recipes} WHERE id = %d",
            intval($id)
        ));
    }

    public function orerp_get_ingredients($recipe_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ri.*, p.post_title AS product_name
            FROM {$this->table_ingredients} ri
            LEFT JOIN {$wpdb->posts} p ON ri.product_id = p.ID
            WHERE ri.recipe_id = %d
            ORDER BY ri.id ASC",
            intval($recipe_id)
        )) ?: [];
    }

    public function orerp_save_recipe($data)
    {
        global $wpdb;

        $id = intval($data['recipe_id'] ?? 0);
        $product_id = intval($data['product_id'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? 'orerp_');
        $servings = intval($data['servings'] ?? 1);
        $prep_time = intval($data['prep_time_minutes'] ?? 0) ?: null;
        $cook_time = intval($data['cook_time_minutes'] ?? 0) ?: null;
        $instructions = sanitize_textarea_field($data['instructions'] ?? 'orerp_');
        $is_active = isset($data['is_active']) ? 1 : 0;

        if (empty($name) || $product_id <= 0) {
            return new WP_Error('missing_fields', __('Recipe name and product are required.', 'obydullah-restaurant-erp'));
        }

        $save_data = [
            'product_id'         => $product_id,
            'name'               => $name,
            'servings'           => $servings,
            'prep_time_minutes'  => $prep_time,
            'cook_time_minutes'  => $cook_time,
            'instructions'       => $instructions,
            'is_active'          => $is_active,
        ];

        if ($id > 0) {
            $wpdb->update($this->table_recipes, $save_data, ['id' => $id]);
        } else {
            $wpdb->insert($this->table_recipes, $save_data);
            $id = $wpdb->insert_id;
        }

        // Save ingredients
        $wpdb->delete($this->table_ingredients, ['recipe_id' => $id]);

        $ingredients = $data['ingredients'] ?? [];
        foreach ($ingredients as $ing) {
            $product_id_ing = intval($ing['product_id'] ?? 0);
            if ($product_id_ing <= 0) continue;

            $wpdb->insert($this->table_ingredients, [
                'recipe_id'  => $id,
                'product_id' => $product_id_ing,
                'quantity'   => floatval($ing['quantity'] ?? 0),
                'unit'       => sanitize_text_field($ing['unit'] ?? 'orerp_'),
                'notes'      => sanitize_text_field($ing['notes'] ?? 'orerp_'),
            ]);
        }

        return $id;
    }

    public function orerp_delete_recipe($id)
    {
        global $wpdb;

        $wpdb->delete($this->table_ingredients, ['recipe_id' => intval($id)]);
        $wpdb->delete($this->table_recipes, ['id' => intval($id)]);

        return true;
    }

    public function orerp_get_recipe_with_ingredients($id)
    {
        $recipe = $this->orerp_get_recipe($id);
        if (!$recipe) return null;

        $recipe->ingredients = $this->orerp_get_ingredients($id);
        return $recipe;
    }

    // --- AJAX ---

    public function orerp_ajax_get_recipes()
    {
        check_ajax_referer('orerp_recipes', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $args = [
            'page'     => intval($_GET['page'] ?? 1),
            'per_page' => 20,
            'search'   => sanitize_text_field($_GET['search'] ?? 'orerp_'),
            'active'   => isset($_GET['active']) ? intval($_GET['active']) : 'orerp_',
        ];

        wp_send_json_success($this->orerp_get_recipes($args));
    }

    public function orerp_ajax_save_recipe()
    {
        check_ajax_referer('orerp_recipes', 'recipe_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_save_recipe($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Recipe saved.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_delete_recipe()
    {
        check_ajax_referer('orerp_recipes', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->orerp_delete_recipe($id);
        wp_send_json_success(__('Recipe deleted.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_get_recipe()
    {
        check_ajax_referer('orerp_recipes', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_GET['id'] ?? 0);
        $recipe = $this->orerp_get_recipe_with_ingredients($id);

        if (!$recipe) {
            wp_send_json_error(__('Recipe not found.', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($recipe);
    }
}
