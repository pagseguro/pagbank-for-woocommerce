<?php
if(!defined('ABSPATH')) {
	exit;
}
?>
<h2><?php _e('PagBank Marketplace', 'pagbank-for-woocommerce'); ?></h2>
<div class="wcfm_clearfix"></div>
<div class="form-field">
    <p class="wcfm_title wcfm_ele"><strong><?php esc_html_e( 'Identificador da conta', 'pagbank-for-woocommerce' ); ?></strong></p>
    <label class="screen-reader-text" for="pagbank_account_id"><?php esc_html_e( 'PayPal Email', 'pagbank-for-woocommerce' ); ?></label>
    <input type="text" id="pagbank_account_id" name="payment[pagbank][account_id]" class="wcfm-text wcfm_ele" value="<?php esc_attr_e( $account_id ); ?>" placeholder="">
</div>
