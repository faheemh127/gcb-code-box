<?php
/**
 * Plugin Name: GCB Code Box (Thumbnail + Copy + Preview)
 * Description: Adds a shortcode to display a YouTube-ratio thumbnail with "Copy Code" and "Preview" buttons. Paste your Gutenberg code between the shortcode tags; set preview URL via attribute.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GCB_Code_Box_Plugin {
	public function __construct() {
		add_shortcode( 'gcb_code_box', [ $this, 'render_shortcode' ] );
	}

	/**
	 * Render the shortcode.
	 *
	 * Usage:
	 * [gcb_code_box preview="https://example.com/my-preview" thumb="https://img.youtube.com/vi/VIDEOID/hqdefault.jpg"]
	 *   <!-- PASTE YOUR GUTENBERG HTML CODE HERE -->
	 * [/gcb_code_box]
	 *
	 * - preview: (optional) URL to open when clicking "Preview"
	 * - thumb:   (optional) thumbnail image URL (16:9). Defaults to an inline SVG placeholder.
	 * - title:   (optional) accessible label for the block
	 */
	public function render_shortcode( $atts, $content = null, $tag = '' ) {
		$atts = shortcode_atts(
			[
				'preview' => '#', // <-- Replace via shortcode attribute later (or keep # to do nothing)
				'thumb'   => '',
				'title'   => 'Code preview block',
			],
			$atts,
			$tag
		);

		// Provide a default 16:9 SVG placeholder if no thumbnail passed.
		$default_svg = 'data:image/svg+xml;utf8,' . rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 720" preserveAspectRatio="xMidYMid slice">
				<rect width="1280" height="720" fill="#111"/>
				<rect x="470" y="260" width="340" height="200" rx="28" fill="#e62117"/>
				<polygon points="560,300 560,420 680,360" fill="#fff"/>
				<text x="640" y="680" fill="#bbb" font-size="48" font-family="Arial" text-anchor="middle">Thumbnail 16:9</text>
			</svg>'
		);

		$thumb_url   = $atts['thumb'] ? esc_url( $atts['thumb'] ) : $default_svg;
		$preview_url = esc_url( $atts['preview'] );
		$title       = sanitize_text_field( $atts['title'] );

		// The code to be copied is the enclosed content. If empty, show demo code + clear instruction comment.
		$code_raw = (string) $content;
		$demo_code = <<<HTML
<!-- DEMO GUTENBERG CODE (replace this by putting your real Gutenberg HTML between the shortcode tags) -->
<!-- Example: A simple group with a heading -->
<!-- wp:group -->
<div class="wp-block-group"><div class="wp-block-group__inner-container">
<!-- wp:heading {"level":2} -->
<h2>Example Gutenberg Section</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Replace this entire block with your own Gutenberg HTML.</p>
<!-- /wp:paragraph -->
</div></div>
<!-- /wp:group -->
HTML;

		$code_to_copy = trim( $code_raw ) !== '' ? $code_raw : $demo_code;

		// Unique ID per instance for accessibility and JS targeting.
		$instance_id = 'gcb-' . wp_generate_uuid4();

		// Enqueue minimal CSS & JS only when shortcode is used.
		$this->enqueue_assets();

		ob_start();
		?>
		<div class="gcb-wrapper" id="<?php echo esc_attr( $instance_id ); ?>" role="group" aria-label="<?php echo esc_attr( $title ); ?>">
			<!-- Thumbnail with preserved 16:9 aspect ratio -->
			<div class="gcb-thumb" aria-hidden="true">
				<div class="gcb-thumb-inner">
					<img src="<?php echo $thumb_url; ?>" alt="" loading="lazy" decoding="async" />
				</div>
			</div>

			<!-- Actions -->
			<div class="gcb-actions">
				<button type="button" class="gcb-btn gcb-copy" data-target="<?php echo esc_attr( $instance_id ); ?>">
					Copy Code
				</button>
				<button type="button" class="gcb-btn gcb-preview" data-url="<?php echo esc_url( $preview_url ); ?>">
					Preview
				</button>
			</div>

			<!-- Hidden textarea holding code to copy -->
			<textarea class="gcb-code" aria-hidden="true"><?php echo esc_textarea( $code_to_copy ); ?></textarea>

			<!-- Live region for user feedback -->
			<div class="gcb-feedback" role="status" aria-live="polite" aria-atomic="true"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue inline CSS & JS (registered cleanly with WP).
	 */
	private function enqueue_assets() {
		// Styles.
		$style_handle = 'gcb-code-box-inline-style';
		if ( ! wp_style_is( $style_handle, 'registered' ) ) {
			wp_register_style( $style_handle, false, [], '1.0.0' );
		}
		$css = <<<CSS
.gcb-wrapper{max-width:880px;margin:1.5rem auto;padding:1rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.gcb-thumb{position:relative;width:100%;border-radius:12px;overflow:hidden;background:#0b0b0b}
.gcb-thumb-inner{position:relative;width:100%;padding-top:56.25%; /* 16:9 */}
.gcb-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.gcb-actions{display:flex;gap:.5rem;justify-content:flex-start;align-items:center;margin-top:.75rem;flex-wrap:wrap}
.gcb-btn{appearance:none;cursor:pointer;border:1px solid #d1d5db;background:#111827;color:#fff;padding:.6rem .9rem;border-radius:.7rem;font-size:14px;line-height:1.1}
.gcb-btn:hover{filter:brightness(1.05)}
.gcb-btn:active{transform:translateY(1px)}
.gcb-code{position:absolute;left:-99999px;top:auto;width:1px;height:1px;opacity:0}
.gcb-feedback{margin-top:.5rem;font-size:13px;color:#065f46;min-height:1em}
CSS;
		wp_add_inline_style( $style_handle, $css );
		wp_enqueue_style( $style_handle );

		// Scripts.
		$script_handle = 'gcb-code-box-inline-script';
		if ( ! wp_script_is( $script_handle, 'registered' ) ) {
			wp_register_script( $script_handle, '', [], '1.0.0', true );
		}
		$js = <<<JS
(function(){
	function copyFromTextarea(textarea){
		var text = textarea.value;
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		// Fallback for older browsers / non-HTTPS
		var tmp = document.createElement('textarea');
		tmp.style.position = 'fixed';
		tmp.style.opacity = '0';
		tmp.value = text;
		document.body.appendChild(tmp);
		tmp.focus();
		tmp.select();
		try { document.execCommand('copy'); } catch(e){}
		document.body.removeChild(tmp);
		return Promise.resolve();
	}

	function setFeedback(wrapper, msg){
		var fb = wrapper.querySelector('.gcb-feedback');
		if (fb){ fb.textContent = msg; }
	}

	document.addEventListener('click', function(e){
		var copyBtn = e.target.closest('.gcb-copy');
		if (copyBtn){
			var wrapper = copyBtn.closest('.gcb-wrapper');
			if (!wrapper) return;
			var ta = wrapper.querySelector('.gcb-code');
			if (!ta) return;
			copyFromTextarea(ta).then(function(){
				setFeedback(wrapper,'Code copied to clipboard.');
				setTimeout(function(){ setFeedback(wrapper,''); }, 2500);
			});
		}

		var prevBtn = e.target.closest('.gcb-preview');
		if (prevBtn){
			var url = prevBtn.getAttribute('data-url') || '#';
			if (url && url !== '#'){
				window.location.href = url; // navigate to your preview URL
			}
		}
	}, false);
})();
JS;
		wp_add_inline_script( $script_handle, $js );
		wp_enqueue_script( $script_handle );
	}
}

new GCB_Code_Box_Plugin();
