<?php global $ttf_one_section_data, $ttf_one_is_js_template; ?>
<?php if ( ! isset( $ttf_one_is_js_template ) || true !== $ttf_one_is_js_template ) : ?>
<div class="ttf-one-section <?php if ( isset( $ttf_one_section_data['data']['state'] ) && 'open' === $ttf_one_section_data['data']['state'] ) echo 'ttf-one-section-open'; ?> ttf-one-section-<?php echo esc_attr( $ttf_one_section_data['section']['id'] ); ?>" id="<?php echo 'ttf-one-section-' . esc_attr( $ttf_one_section_data['data']['id'] ); ?>" data-id="<?php echo esc_attr( $ttf_one_section_data['data']['id'] ); ?>" data-section-type="<?php echo esc_attr( $ttf_one_section_data['section']['id'] ); ?>">
<?php endif; ?>
	<div class="ttf-one-section-header">
		<?php
			$header_title = '';
			if ( isset( $ttf_one_section_data['data']['label'] ) ) {
				$header_title = $ttf_one_section_data['data']['label'];
			}
		?>
		<h3>
			<span class="ttf-one-section-header-title"><?php echo esc_html( $header_title ); ?></span><em><?php echo ( esc_html( $ttf_one_section_data['section']['label'] ) ); ?></em>
		</h3>
		<a href="#" class="ttf-one-section-toggle" title="<?php esc_attr_e( 'Click to toggle', 'ttf-one' ); ?>">
			<div class="handlediv"></div>
		</a>
	</div>
	<div class="clear"></div>
	<div class="ttf-one-section-body">
		<input type="hidden" value="<?php echo $ttf_one_section_data['section']['id']; ?>" name="<?php echo ttf_one_get_section_name( $ttf_one_section_data, $ttf_one_is_js_template ); ?>[section-type]" />
