<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpp_Backend' ) ) {
	class Wpcpp_Backend {
		protected static $rules = [];
		protected static $settings = [];
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$rules    = get_option( 'wpcpp_rules', [] );
			self::$settings = get_option( 'wpcpp_settings', [] );

			// Settings
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );

			// Enqueue scripts
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

			// Add settings link
			add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
			add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

			// AJAX
			add_action( 'wp_ajax_wpcpp_add_rule', [ $this, 'ajax_add_rule' ] );
			add_action( 'wp_ajax_wpcpp_add_time', [ $this, 'ajax_add_time' ] );
			add_action( 'wp_ajax_wpcpp_search_term', [ $this, 'ajax_search_term' ] );
		}

		function register_settings() {
			// rules
			register_setting( 'wpcpp_settings', 'wpcpp_rules' );
			// settings
			register_setting( 'wpcpp_settings', 'wpcpp_settings' );
		}

		function admin_menu() {
			add_submenu_page( 'wpclever', esc_html__( 'WPC Advanced Password Protect', 'wpc-advanced-password-protect' ), esc_html__( 'Advanced Password Protect', 'wpc-advanced-password-protect' ), 'manage_options', 'wpclever-wpcpp', [
				$this,
				'admin_menu_content'
			] );
		}

		function admin_menu_content() {
			$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
			?>
            <div class="wpclever_settings_page wrap">
                <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Advanced Password Protect', 'wpc-advanced-password-protect' ) . ' ' . esc_html( WPCPP_VERSION ) . ' ' . ( defined( 'WPCPP_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-advanced-password-protect' ) . '</span>' : '' ); ?></h1>
                <div class="wpclever_settings_page_desc about-text">
                    <p>
						<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-advanced-password-protect' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                        <br/>
                        <a href="<?php echo esc_url( WPCPP_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-advanced-password-protect' ); ?></a> |
                        <a href="<?php echo esc_url( WPCPP_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-advanced-password-protect' ); ?></a> |
                        <a href="<?php echo esc_url( WPCPP_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-advanced-password-protect' ); ?></a>
                    </p>
                </div>
				<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-advanced-password-protect' ); ?></p>
                    </div>
				<?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpp&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
							<?php esc_html_e( 'Settings', 'wpc-advanced-password-protect' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpp&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
							<?php esc_html_e( 'Premium Version', 'wpc-advanced-password-protect' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
							<?php esc_html_e( 'Essential Kit', 'wpc-advanced-password-protect' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
					<?php if ( $active_tab === 'settings' ) {
						$expiry            = self::get_setting( 'expiry', 7 );
						$title             = self::get_setting( 'title', '' );
						$message           = self::get_setting( 'message', '' );
						$label             = self::get_setting( 'label', '' );
						$button            = self::get_setting( 'button', '' );
						$unpurchasable     = self::get_setting( 'unpurchasable', 'yes' );
						$attempt           = self::get_setting( 'attempt', 5 );
						$incorrect_message = self::get_setting( 'incorrect_message', '' );
						$attempt_message   = self::get_setting( 'attempt_message', '' );
						?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th colspan="2"><?php esc_html_e( 'Protection rules', 'wpc-advanced-password-protect' ); ?></th>
                                </tr>
                                <tr>
                                    <th>
										<?php esc_html_e( 'Rules', 'wpc-advanced-password-protect' ); ?>
                                    </th>
                                    <td>
                                        <p class="description"><?php esc_html_e( 'Rules will be checked from the top of the list down to end. When matched conditions are found, the chosen protect method(s), (be it passwords, user roles and/or users) will be activated for applicable subjects.', 'wpc-advanced-password-protect' ); ?></p>
                                        <div class="wpcpp_current_time">
											<?php esc_html_e( 'Current time', 'wpc-advanced-password-protect' ); ?>
                                            <code><?php echo esc_html( current_time( 'l' ) ); ?></code>
                                            <code><?php echo esc_html( current_time( 'm/d/Y' ) ); ?></code>
                                            <code><?php echo esc_html( current_time( 'h:i a' ) ); ?></code>
                                            <code><?php echo esc_html( esc_html__( 'Week No.', 'wpc-advanced-password-protect' ) . ' ' . current_time( 'W' ) ); ?></code>
                                            <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Date/time settings', 'wpc-advanced-password-protect' ); ?></a>
                                        </div>
                                        <div class="wpcpp_rules">
											<?php
											if ( is_array( self::$rules ) && ! empty( self::$rules ) ) {
												foreach ( self::$rules as $key => $action ) {
													self::rule( $key, $action );
												}
											} else {
												self::rule( 0, [], true );
											}
											?>
                                        </div>
                                        <div class="wpcpp_add_rule">
                                            <div>
                                                <a href="#" class="wpcpp_new_rule button">
													<?php esc_html_e( '+ Add rule', 'wpc-advanced-password-protect' ); ?>
                                                </a> <a href="#" class="wpcpp_expand_all">
													<?php esc_html_e( 'Expand All', 'wpc-advanced-password-protect' ); ?>
                                                </a> <a href="#" class="wpcpp_collapse_all">
													<?php esc_html_e( 'Collapse All', 'wpc-advanced-password-protect' ); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2"><?php esc_html_e( 'General', 'wpc-advanced-password-protect' ); ?></th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Force unpurchasable', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <select name="wpcpp_settings[unpurchasable]">
                                            <option value="yes" <?php selected( $unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-advanced-password-protect' ); ?></option>
                                            <option value="no" <?php selected( $unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-advanced-password-protect' ); ?></option>
                                        </select>
                                        <span class="description"><?php esc_html_e( 'Force all protected products to become unpurchasable. This offers additional protection in case protected products appear in special product listings.', 'wpc-advanced-password-protect' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Password authentication interval', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="number" name="wpcpp_settings[expiry]" min="0" max="365" step="1" value="<?php echo esc_attr( $expiry ); ?>"/> days
                                        <p class="description"><?php esc_html_e( 'How long does it take for users to be required to enter the password again? Enter “0” to make the authentication expire immediately at the end of the browsing session (or when users close the browser).', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Login attempt limit', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <select name="wpcpp_settings[attempt]">
											<?php
											for ( $i = 0; $i < 11; $i ++ ) {
												echo '<option value="' . esc_attr( $i ) . '" ' . selected( $attempt, $i, false ) . '>' . esc_html( $i ) . '</option>';
											}
											?>
                                        </select>
                                        <span class="description"><?php esc_html_e( 'Limit how many successive failed login attempts can be made. When the limit is reached, users have to wait 30 minutes before they can try again. Choose “0” to disable this feature.', 'wpc-advanced-password-protect' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Incorrect password alert', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="text" class="text large-text" name="wpcpp_settings[incorrect_message]" value="<?php echo esc_attr( $incorrect_message ); ?>" placeholder="<?php esc_attr_e( 'Incorrect password! Please try again.', 'wpc-advanced-password-protect' ); ?>"/>
                                        <p class="description"><?php esc_html_e( 'The message displayed when the entered password is incorrect.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exceed attempt limit alert', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="text" class="text large-text" name="wpcpp_settings[attempt_message]" value="<?php echo esc_attr( $attempt_message ); ?>" placeholder="<?php esc_attr_e( 'You have reached the attempt limit! Please try again after 30 minutes.', 'wpc-advanced-password-protect' ); ?>"/>
                                        <p class="description"><?php esc_html_e( 'The message displayed when users reach the limit of failed login attempts.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2"><?php esc_html_e( 'Password form', 'wpc-advanced-password-protect' ); ?></th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Title', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="text" class="text large-text" name="wpcpp_settings[title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Login Required', 'wpc-advanced-password-protect' ); ?>"/>
                                        <p class="description"><?php esc_html_e( 'The title of the login page. You can use the placeholder {title} to show the name of the current product or category. You can configure a custom title for each rule.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Message', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <textarea class="large-text" name="wpcpp_settings[message]" placeholder="<?php esc_attr_e( 'This content is password protected. To view it please enter your password below:', 'wpc-advanced-password-protect' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'The message for the login form. You can configure a custom message for each rule.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Label', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="text" class="text large-text" name="wpcpp_settings[label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Password:', 'wpc-advanced-password-protect' ); ?>"/>
                                        <p class="description"><?php esc_html_e( 'The label shown next to the password box.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Button text', 'wpc-advanced-password-protect' ); ?></th>
                                    <td>
                                        <input type="text" class="text large-text" name="wpcpp_settings[button]" value="<?php echo esc_attr( $button ); ?>" placeholder="<?php esc_attr_e( 'Login', 'wpc-advanced-password-protect' ); ?>"/>
                                        <p class="description"><?php esc_html_e( 'The text for the login button.', 'wpc-advanced-password-protect' ); ?></p>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wpcpp_settings' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/wpc-advanced-password-protect?utm_source=pro&utm_medium=wpcpp&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-advanced-password-protect</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Advanced protection by user role & by user.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
					<?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}

		function admin_enqueue_scripts( $hook ) {
			if ( apply_filters( 'wpcpp_ignore_backend_scripts', false, $hook ) ) {
				return null;
			}

			// wpcdpk
			wp_enqueue_style( 'wpcdpk', WPCPP_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
			wp_enqueue_script( 'wpcdpk', WPCPP_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WPCPP_VERSION, true );

			// backend
			wp_enqueue_style( 'wpcpp-backend', WPCPP_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCPP_VERSION );
			wp_enqueue_script( 'wpcpp-backend', WPCPP_URI . 'assets/js/backend.js', [
				'jquery',
				'jquery-ui-sortable',
				'wc-enhanced-select',
				'selectWoo',
			], WPCPP_VERSION, true );
			wp_localize_script( 'wpcpp-backend', 'wpcpp_vars', [
				'nonce' => wp_create_nonce( 'wpcpp-security' )
			] );
		}

		function action_links( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCPP_FILE );
			}

			if ( $plugin === $file ) {
				$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpp&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-advanced-password-protect' ) . '</a>';
				$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpp&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-advanced-password-protect' ) . '</a>';
				array_unshift( $links, $settings );
			}

			return (array) $links;
		}

		function row_meta( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCPP_FILE );
			}

			if ( $plugin === $file ) {
				$row_meta = [
					'support' => '<a href="' . esc_url( WPCPP_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-advanced-password-protect' ) . '</a>',
				];

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		function rule( $key = 0, $rule = [], $active = false ) {
			if ( empty( $key ) || is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			$rule_df = [
				'name'      => '',
				'title'     => '',
				'message'   => '',
				'apply'     => 'apply_all',
				'apply_val' => [],
				'timer'     => [],
				'password'  => [],
				'roles'     => [],
				'users'     => [],
			];

			$rule_data   = array_merge( $rule_df, $rule );
			$name        = $rule_data['name'];
			$apply       = $rule_data['apply'];
			$apply_val   = $rule_data['apply_val'];
			$conditional = $rule_data['timer'];
			$password    = $rule_data['password'];
			$roles       = $rule_data['roles'];
			$users       = $rule_data['users'];
			$title       = $rule_data['title'];
			$message     = $rule_data['message'];
			?>
            <div class="wpcpp_rule <?php echo esc_attr( $active ? 'active' : '' ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
                <div class="wpcpp_rule_heading">
                    <span class="wpcpp_rule_move"></span>
                    <span class="wpcpp_rule_label"><span class="wpcpp_rule_label_name"><?php echo esc_html( '#' . $key ); ?></span><span class="wpcpp_rule_label_apply"></span></span>
                    <a href="#" class="wpcpp_rule_duplicate"><?php esc_html_e( 'duplicate', 'wpc-advanced-password-protect' ); ?></a>
                    <a href="#" class="wpcpp_rule_remove"><?php esc_html_e( 'remove', 'wpc-advanced-password-protect' ); ?></a>
                </div>
                <div class="wpcpp_rule_content">
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Name', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td wpcpp_rule_td">
                            <p class="description"><?php esc_html_e( 'For management use only.', 'wpc-advanced-password-protect' ); ?></p>
                            <input type="text" class="text large-text wpcpp_rule_name_input" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][name]" data-name="<?php echo esc_attr( '#' . $key ); ?>" value="<?php echo esc_attr( $name ); ?>"/>
                        </div>
                    </div>
                    <div class="wpcpp_tr wpcpp_tr_heading">
                        <div class="wpcpp_td">
                            <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Applicable conditions', 'wpc-advanced-password-protect' ); ?>
                        </div>
                    </div>
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Apply for', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td wpcpp_rule_td">
                            <input type="hidden" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][type]" value="global"/>
                            <select class="wpcpp_apply_selector" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][apply]">
                                <option value="apply_all" <?php selected( $apply, 'apply_all' ); ?>><?php esc_html_e( 'All products', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="apply_product" <?php selected( $apply, 'apply_product' ); ?>><?php esc_html_e( 'Selected products', 'wpc-advanced-password-protect' ); ?></option>
								<?php
								$taxonomies = get_object_taxonomies( 'product', 'objects' );

								foreach ( $taxonomies as $taxonomy ) {
									echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $apply, $taxonomy->name ) . '>' . esc_html( $taxonomy->label ) . '</option>';
								}
								?>
                            </select>
                        </div>
                    </div>
                    <div class="wpcpp_tr hide_apply show_if_apply_product">
                        <div class="wpcpp_th"><?php esc_html_e( 'Products', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td wpcpp_rule_td">
							<?php $product_ids = ! empty( $apply_val['products'] ) ? $apply_val['products'] : []; ?>
                            <select class="wc-product-search wpcpp_products_select" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][apply_val][products][]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-advanced-password-protect' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-val="<?php echo esc_attr( implode( ',', $product_ids ) ); ?>" multiple>
								<?php
								if ( ! empty( $product_ids ) ) {
									foreach ( $product_ids as $product_id ) {
										if ( $product = wc_get_product( $product_id ) ) {
											echo '<option value="' . esc_attr( $product_id ) . '" selected>' . esc_html( $product->get_formatted_name() ) . '</option>';
										}
									}
								}
								?>
                            </select>
                        </div>
                    </div>
                    <div class="wpcpp_tr show_apply hide_if_apply_all hide_if_apply_product">
                        <div class="wpcpp_th wpcpp_apply_text"><?php esc_html_e( 'Terms', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td wpcpp_rule_td">
							<?php $term_slugs = ! empty( $apply_val['terms'] ) ? $apply_val['terms'] : []; ?>
                            <select class="wpcpp_terms_select" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][apply_val][terms][]" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $term_slugs ) ); ?>" multiple>
								<?php
								if ( ! empty( $term_slugs ) ) {
									$taxonomy = $apply;

									foreach ( $term_slugs as $ts ) {
										if ( $term = get_term_by( 'slug', $ts, $taxonomy ) ) {
											echo '<option value="' . esc_attr( $ts ) . '" selected>' . esc_html( $term->name ) . '</option>';
										}
									}
								}
								?>
                            </select>
                        </div>
                    </div>
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Time', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
                            <div class="wpcpp_timer">
                                <p class="description"><?php esc_html_e( '* Configure date and time of the rule that must match all listed conditions.', 'wpc-advanced-password-protect' ); ?></p>
								<?php
								if ( is_array( $conditional ) && ( count( $conditional ) > 0 ) ) {
									foreach ( $conditional as $conditional_key => $conditional_item ) {
										self::time( $key, $conditional_key, $conditional_item );
									}
								} else {
									self::time( $key );
								}
								?>
                            </div>
                            <div class="wpcpp_add_time">
                                <a href="#" class="wpcpp_new_time"><?php esc_html_e( '+ Add time', 'wpc-advanced-password-protect' ); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="wpcpp_tr wpcpp_tr_heading">
                        <div class="wpcpp_td">
                            <span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Protect by password or restrict to', 'wpc-advanced-password-protect' ); ?>
                        </div>
                    </div>
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Password', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
							<?php
							echo '<select name="wpcpp_rules[' . esc_attr( $key ) . '][password][]" class="wpcpp_password_select" multiple>';

							if ( is_array( $password ) && ! empty( $password ) ) {
								foreach ( $password as $pw ) {
									echo '<option value="' . esc_attr( $pw ) . '" selected>' . esc_html( $pw ) . '</option>';
								}
							}

							echo '</select>';
							?>
                            </select>
                        </div>
                    </div>
                    <div class="wpcpp_tr wpcpp_premium_only"><!-- wpcpp_premium_only -->
                        <div class="wpcpp_th"><?php esc_html_e( 'User roles', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
							<?php
							global $wp_roles;

							if ( is_array( $roles ) ) {
								$roles_arr = $roles;
							} elseif ( is_string( $roles ) ) {
								$roles_arr = explode( ',', $roles );
							} else {
								$roles_arr = [];
							}

							echo '<select class="wpcpp_roles_select" name="wpcpp_rules[' . esc_attr( $key ) . '][roles][]" multiple>';
							echo '<option value="wpcpp_user" ' . ( in_array( 'wpcpp_user', $roles_arr ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-advanced-password-protect' ) . '</option>';
							echo '<option value="wpcpp_guest" ' . ( in_array( 'wpcpp_guest', $roles_arr ) || in_array( 'guest', $roles_arr ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-advanced-password-protect' ) . '</option>';

							if ( ! empty( $wp_roles->roles ) ) {
								foreach ( $wp_roles->roles as $role => $details ) {
									echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $roles_arr ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
								}
							}

							echo '</select>';
							?>
                            <p class="description" style="color: #c9356e">This feature is available on the Premium Version only.</p>
                        </div>
                    </div>
                    <div class="wpcpp_tr wpcpp_premium_only"><!-- wpcpp_premium_only -->
                        <div class="wpcpp_th"><?php esc_html_e( 'Users', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
							<?php
							echo '<select class="wpcpp_users_select" name="wpcpp_rules[' . esc_attr( $key ) . '][users][]" multiple>';

							if ( ! empty( $users ) ) {
								foreach ( $users as $user ) {
									if ( $user_data = get_userdata( $user ) ) {
										echo '<option value="' . esc_attr( $user ) . '" selected>' . esc_html( $user_data->user_nicename ) . '</option>';
									}
								}
							}

							echo '</select>';
							?>
                            <p class="description" style="color: #c9356e">This feature is available on the Premium Version only.</p>
                        </div>
                    </div>
                    <div class="wpcpp_tr wpcpp_tr_heading">
                        <div class="wpcpp_td">
                            <span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Password form', 'wpc-advanced-password-protect' ); ?>
                        </div>
                    </div>
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Title', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
                            <input type="text" class="text large-text" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][title]" value="<?php echo esc_attr( $title ); ?>"/>
                        </div>
                    </div>
                    <div class="wpcpp_tr">
                        <div class="wpcpp_th"><?php esc_html_e( 'Message', 'wpc-advanced-password-protect' ); ?></div>
                        <div class="wpcpp_td">
                            <textarea class="text large-text" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][message]"><?php echo esc_textarea( $message ); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}

		function time( $key = 0, $time_key = 0, $time_data = [] ) {
			if ( empty( $key ) || is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			if ( empty( $time_key ) || is_numeric( $time_key ) ) {
				$time_key = self::generate_key();
			}

			$type = ! empty( $time_data['type'] ) ? $time_data['type'] : 'every_day';
			$val  = ! empty( $time_data['val'] ) ? $time_data['val'] : '';
			$date = $date_time = $date_multi = $date_range = $from = $to = $time = $weekday = $monthday = $weekno = $monthno = $number = '';

			switch ( $type ) {
				case 'date_on':
				case 'date_before':
				case 'date_after':
					$date = $val;
					break;
				case 'date_time_before':
				case 'date_time_after':
					$date_time = $val;
					break;
				case 'date_multi':
					$date_multi = $val;
					break;
				case 'date_range':
					$date_range = $val;
					break;
				case 'time_range':
					$time_range = array_map( 'trim', explode( '-', (string) $val ) );
					$from       = ! empty( $time_range[0] ) ? $time_range[0] : '';
					$to         = ! empty( $time_range[1] ) ? $time_range[1] : '';
					break;
				case 'time_before':
				case 'time_after':
					$time = $val;
					break;
				case 'weekly_every':
					$weekday = $val;
					break;
				case 'week_no':
					$weekno = $val;
					break;
				case 'monthly_every':
					$monthday = $val;
					break;
				case 'month_no':
					$monthno = $val;
					break;
				default:
					$val = '';
			}
			?>
            <div class="wpcpp_time">
                <input type="hidden" class="wpcpp_time_val" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][timer][<?php echo esc_attr( $time_key ); ?>][val]" value="<?php echo esc_attr( $val ); ?>"/>
                <span class="wpcpp_time_remove">&times;</span> <span>
							<select class="wpcpp_time_type" name="wpcpp_rules[<?php echo esc_attr( $key ); ?>][timer][<?php echo esc_attr( $time_key ); ?>][type]">
								<option value=""><?php esc_html_e( 'Choose the time', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_on" data-show="date" <?php selected( $type, 'date_on' ); ?>><?php esc_html_e( 'On the date', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="date_time_before" data-show="date_time" <?php selected( $type, 'date_time_before' ); ?>><?php esc_html_e( 'Before date & time', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_time_after" data-show="date_time" <?php selected( $type, 'date_time_after' ); ?>><?php esc_html_e( 'After date & time', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_before" data-show="date" <?php selected( $type, 'date_before' ); ?>><?php esc_html_e( 'Before date', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_after" data-show="date" <?php selected( $type, 'date_after' ); ?>><?php esc_html_e( 'After date', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_multi" data-show="date_multi" <?php selected( $type, 'date_multi' ); ?>><?php esc_html_e( 'Multiple dates', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_range" data-show="date_range" <?php selected( $type, 'date_range' ); ?>><?php esc_html_e( 'Date range', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_even" data-show="none" <?php selected( $type, 'date_even' ); ?>><?php esc_html_e( 'All even dates', 'wpc-advanced-password-protect' ); ?></option>
								<option value="date_odd" data-show="none" <?php selected( $type, 'date_odd' ); ?>><?php esc_html_e( 'All odd dates', 'wpc-advanced-password-protect' ); ?></option>
								<option value="time_range" data-show="time_range" <?php selected( $type, 'time_range' ); ?>><?php esc_html_e( 'Daily time range', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="time_before" data-show="time" <?php selected( $type, 'time_before' ); ?>><?php esc_html_e( 'Daily before time', 'wpc-advanced-password-protect' ); ?></option>
								<option value="time_after" data-show="time" <?php selected( $type, 'time_after' ); ?>><?php esc_html_e( 'Daily after time', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="weekly_every" data-show="weekday" <?php selected( $type, 'weekly_every' ); ?>><?php esc_html_e( 'Weekly on every', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="week_even" data-show="none" <?php selected( $type, 'week_even' ); ?>><?php esc_html_e( 'All even weeks', 'wpc-advanced-password-protect' ); ?></option>
								<option value="week_odd" data-show="none" <?php selected( $type, 'week_odd' ); ?>><?php esc_html_e( 'All odd weeks', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="week_no" data-show="weekno" <?php selected( $type, 'week_no' ); ?>><?php esc_html_e( 'On week No.', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="monthly_every" data-show="monthday" <?php selected( $type, 'monthly_every' ); ?>><?php esc_html_e( 'Monthly on the', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="month_no" data-show="monthno" <?php selected( $type, 'month_no' ); ?>><?php esc_html_e( 'On month No.', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="every_day" data-show="none" <?php selected( $type, 'every_day' ); ?>><?php esc_html_e( 'Everyday', 'wpc-advanced-password-protect' ); ?></option>
							</select>
						</span> <span class="wpcpp_hide wpcpp_show_if_date_time">
							<input value="<?php echo esc_attr( $date_time ); ?>" class="wpcpp_dpk_date_time wpcpp_date_time_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_date">
							<input value="<?php echo esc_attr( $date ); ?>" class="wpcpp_dpk_date wpcpp_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_date_range">
							<input value="<?php echo esc_attr( $date_range ); ?>" class="wpcpp_dpk_date_range wpcpp_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_date_multi">
							<input value="<?php echo esc_attr( $date_multi ); ?>" class="wpcpp_dpk_date_multi wpcpp_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_time_range">
							<input value="<?php echo esc_attr( $from ); ?>" class="wpcpp_dpk_time wpcpp_time_from wpcpp_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="from"/>
							<input value="<?php echo esc_attr( $to ); ?>" class="wpcpp_dpk_time wpcpp_time_to wpcpp_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="to"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_time">
							<input value="<?php echo esc_attr( $time ); ?>" class="wpcpp_dpk_time wpcpp_time_on wpcpp_time_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="wpcpp_hide wpcpp_show_if_weekday">
							<select class="wpcpp_weekday">
                                <option value="mon" <?php selected( $weekday, 'mon' ); ?>><?php esc_html_e( 'Monday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="tue" <?php selected( $weekday, 'tue' ); ?>><?php esc_html_e( 'Tuesday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="wed" <?php selected( $weekday, 'wed' ); ?>><?php esc_html_e( 'Wednesday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="thu" <?php selected( $weekday, 'thu' ); ?>><?php esc_html_e( 'Thursday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="fri" <?php selected( $weekday, 'fri' ); ?>><?php esc_html_e( 'Friday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="sat" <?php selected( $weekday, 'sat' ); ?>><?php esc_html_e( 'Saturday', 'wpc-advanced-password-protect' ); ?></option>
                                <option value="sun" <?php selected( $weekday, 'sun' ); ?>><?php esc_html_e( 'Sunday', 'wpc-advanced-password-protect' ); ?></option>
                            </select>
						</span> <span class="wpcpp_hide wpcpp_show_if_monthday">
							<select class="wpcpp_monthday">
                                <?php for ( $i = 1; $i < 32; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthday === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
                                } ?>
                            </select>
						</span> <span class="wpcpp_hide wpcpp_show_if_weekno">
							<select class="wpcpp_weekno">
                                <?php
                                for ( $i = 1; $i < 54; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $weekno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
                                }
                                ?>
                            </select>
						</span> <span class="wpcpp_hide wpcpp_show_if_monthno">
							<select class="wpcpp_monthno">
                                <?php
                                for ( $i = 1; $i < 13; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
                                }
                                ?>
                            </select>
						</span> <span class="wpcpp_hide wpcpp_show_if_number">
							<input type="number" step="1" min="0" class="wpcpp_number" value="<?php echo esc_attr( (int) $number ); ?>"/>
						</span>
            </div>
			<?php
		}

		function ajax_add_rule() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcpp-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$action    = [];
			$form_data = sanitize_text_field( $_POST['form_data'] ?? '' );

			if ( ! empty( $form_data ) ) {
				$form_action = [];
				parse_str( $form_data, $form_action );

				if ( isset( $form_action['wpcpp_rules'] ) && is_array( $form_action['wpcpp_rules'] ) ) {
					$action = reset( $form_action['wpcpp_rules'] );
				}
			}

			self::rule( 0, $action, true );

			wp_die();
		}

		function ajax_add_time() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcpp-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$key = ! empty( $_POST['key'] ) && ! is_numeric( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : self::generate_key();

			self::time( $key );
			wp_die();
		}

		function ajax_search_term() {
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpcpp-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$return = [];
			$args   = [
				'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => false,
				'fields'     => 'all',
				'name__like' => sanitize_text_field( $_REQUEST['q'] ),
			];
			$terms  = get_terms( $args );

			if ( count( $terms ) ) {
				foreach ( $terms as $term ) {
					$return[] = [ $term->slug, $term->name ];
				}
			}

			wp_send_json( $return );
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'wpcpp_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wpcpp_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wpcpp_generate_key', $key );
		}

		public static function get_settings() {
			return apply_filters( 'wpcpp_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			$settings = self::get_settings();

			if ( ! empty( $settings ) ) {
				if ( isset( $settings[ $name ] ) && ( $settings[ $name ] !== '' ) ) {
					$setting = $settings[ $name ];
				} else {
					$setting = $default;
				}
			} else {
				$setting = get_option( 'wpcpp_' . $name, $default );
			}

			return apply_filters( 'wpcpp_get_setting', $setting, $name, $default );
		}

		public static function get_rules() {
			return self::$rules;
		}
	}

	function Wpcpp_Backend() {
		return Wpcpp_Backend::instance();
	}

	Wpcpp_Backend();
}
