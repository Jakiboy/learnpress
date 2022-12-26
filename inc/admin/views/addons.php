<?php
/**
 * Template show list addons of LearnPress
 *
 * @version 1.0.0
 * @since 4.2.1
 */

use LearnPress\Helpers\Template;

defined( 'ABSPATH' ) || exit();

if ( ! isset( $addons ) ) {
	return;
}

include_once ABSPATH . 'wp-admin/includes/plugin.php';

$total_addon_free      = 0;
$total_addon_paid      = 0;
$total_addon_installed = 0;
$total_addon_activated = 0;
$total_addon_update    = 0;
$plugins_installed     = get_plugins();
$plugins_activated     = get_option( 'active_plugins' );
$active_tab            = ! empty( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : 'all';
?>
<div class="lp-addons-wrapper">
	<div id="lp-addons">
		<?php
		foreach ( $addons as $slug => $addon ) {
			$addon->slug     = $slug;
			$is_installed    = false;
			$is_activated    = false;
			$is_updated      = false;
			$is_free         = $addon->is_free;
			$addon_base      = "$slug/$slug.php";
			$version_latest  = $addon->version;
			$version_current = 0;
			$data            = array(
				'name'  => $addon->slug,
				'id'    => $addon->slug,
				'value' => 0,
				'extra' => 'data-addon="' . htmlentities( json_encode( $addon ) ) . '"',
			);

			if ( 1 == $addon->is_free ) {
				$total_addon_free ++;
			} else {
				$total_addon_paid ++;
			}

			if ( isset( $plugins_installed[ $addon_base ] ) ) {
				$is_installed    = true;
				$version_current = $plugins_installed[ $addon_base ]['Version'];
				$total_addon_installed ++;
			}

			if ( in_array( $addon_base, $plugins_activated ) ) {
				$is_activated = true;
				$total_addon_activated ++;
			}

			if ( $is_installed && version_compare( $version_current, $version_latest, '<' ) ) {
				$total_addon_update ++;
				$is_updated = true;
			}

			switch ( $active_tab ) {
				case 'installed':
					if ( ! $is_installed ) {
						continue 2;
					}
					break;
				case 'paid':
					if ( $is_free ) {
						continue 2;
					}
					break;
				case 'free':
					if ( ! $is_free ) {
						continue 2;
					}
					break;
				case 'update':
					if ( ! $is_updated ) {
						continue 2;
					}
					break;
				default:
					break;
			}
			?>
			<div class="lp-addon-item">
				<div class="lp-addon-item__content">
					<img src="<?php echo $addon->image; ?>" alt="<?php echo $addon->name; ?>"/>
					<h3>
						<a href="<?php echo $addon->link; ?>" target="_blank" rel="noopener">
							<?php echo $addon->name; ?>
						</a>
					</h3>
					<?php
					if ( $version_current ) {
						echo "<h4>Version $version_current</h4>";
					} else {
						echo "<h4>Version $version_latest</h4>";
					}
					echo "<h4>Require LP $addon->require_lp</h4>";
					echo "<h4>Free $addon->is_free</h4>";
					?>
					<p title="<?php echo $addon->description; ?>"><?php echo $addon->description; ?></p>
				</div>
				<div class="lp-addon-item__actions">
					<div class="lp-addon-item__actions__left">
						<?php
						if ( $is_installed ) {
							if ( $is_activated ) {
								echo '<button>Settings</button>';
							}
							if ( $is_updated ) {
								echo '<button class="btn-addon-action" data-action="update" ' . $data['extra'] . '>Update</button>';
							}
						} else {
							echo '<button class="btn-addon-action" data-action="install" ' . $data['extra'] . '>Install</button>';
						}
						?>
					</div>
					<div class="lp-addon-item__actions__right">
						<?php
						if ( $is_installed ) {
							if ( $is_activated ) {
								$data['value']  = 1;
								$data['extra'] .= ' data-action="deactivate"';
							} else {
								$data['extra'] .= ' data-action="activate"';
							}

							Template::instance()->get_template( LP_PLUGIN_PATH . '/inc/admin/meta-box/fields/toggle-switch.php', compact( 'data' ) );
						} else {

						}
						?>
					</div>
				</div>
			</div>
			<?php
		}
		?>
	</div>
	<h2 class="lp-nav-tab-wrapper" style="display: none">
		<?php
		$tabs = array(
			'all'       => sprintf( __( 'All (%d)', 'learnpress' ), count( (array) $addons ) ),
			'installed' => sprintf( __( 'Installed (%d)', 'learnpress' ), $total_addon_installed ),
			'paid'      => sprintf( __( 'Paid (%d)', 'learnpress' ), $total_addon_paid ),
			'free'      => sprintf( __( 'Free (%d)', 'learnpress' ), $total_addon_free ),
			'update'    => sprintf( __( 'Update (%d)', 'learnpress' ), $total_addon_update ),
			'more'      => __( 'Get more', 'learnpress' ),
		);
		foreach ( $tabs as $tab => $name ) {
			?>
			<?php
			$obj_tab = false;

			if ( is_object( $name ) ) {
				$obj_tab = $name;
				$name    = $obj_tab->text;
				$tab     = $obj_tab->id;
			}

			$active_class = ( $tab == $active_tab ) ? ' nav-tab-active' : '';
			$tab_title    = apply_filters( 'learn-press/admin/submenu-heading-tab-title', $name, $tab );
			?>

			<?php if ( $active_class ) { ?>
				<span
					class="nav-tab<?php echo esc_attr( $active_class ); ?>"><?php echo esc_html( $tab_title ); ?></span>
			<?php } else { ?>
				<a class="nav-tab"
					href="?page=learn-press-addons&tab=<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $tab_title ); ?></a>
			<?php } ?>
		<?php } ?>
		<div class="">
			<input type="text" placeholder="Search name addon">
		</div>
	</h2>
</div>
