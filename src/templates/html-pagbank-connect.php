<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	use PagBank_WooCommerce\Presentation\Connect;

	$applications = Connect::get_connect_applications();
	$nonce = wp_create_nonce( 'pagbank_woocommerce_oauth' );
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
		<?php
			echo $wc_settings->get_tooltip_html( $data );
		?>
		</label>
	</th>
	<td class="forminp">
		<fieldset>
			<!-- sandbox modal -->
			<div id="<?php echo esc_attr( $field_key ); ?>-modal-sandbox" class="pagbank-connect-modal hidden">
				<div class="pagbank-connect-modal-wrapper">
					<div class="pagbank-connect-modal-content">
						<button class="pagbank-connect-modal-close-button" type="button" data-modal-close-button>&#10005;</button>
						<p>Você está em modo de testes, portanto nenhuma taxa será aplicada. Clique no botão abaixo para continuar.</p>

						<div class="pagbank-connect-modal-condition-sandbox">
							<button
								type="button"
								data-connect-application-id="fa1553af-5f0c-4ff2-92c3-a0dd8984b6a1"
								data-connect-application-environment="sandbox"
								data-connect-nonce="<?php echo esc_attr( $nonce ); ?>"
							>
								<?php esc_html_e('Continuar', 'pagbank-for-woocommerce'); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- production modal -->
			<div id="<?php echo esc_attr( $field_key ); ?>-modal-production" class="pagbank-connect-modal hidden">
				<div class="pagbank-connect-modal-wrapper">
					<div class="pagbank-connect-modal-content">
						<button class="pagbank-connect-modal-close-button" type="button" data-modal-close-button>&#10005;</button>
						<p>Para conectar o método de pagamento, é necessário que você possua uma conta PagBank. Caso ainda não tenha a conta, <a href="https://cadastro.pagseguro.uol.com.br/" target="_blank">clique aqui</a> para criar uma nova.</p>
						<p>Escolha o plano de recebimento que mais combina com o seu negócio:</p>

						<table>
							<thead>
								<tr>
									<th>Receba em 14 dias</th>
									<th>Receba em 30 dias</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>
										<ul>
											<li>Sem mensalidade e taxa de adesão</li>
											<li>4,39% + R$ 0,40 no crédito à vista e parcelado</li>
											<li>0,99% no PIX com recebimento em D0</li>
											<li>R$ 2,99 no boleto com recebimento em D2</li>
											<li>Antecipe o recebimento quando quiser por +2,99%</li>
										</ul>
										<button
											type="button"
											data-connect-application-id="31241905-5426-4f88-a140-4416a2cab404"
											data-connect-application-environment="production"
											data-connect-nonce="<?php echo esc_attr( $nonce ); ?>"
										>
											<?php esc_html_e('Escolher este', 'pagbank-for-woocommerce'); ?>
										</button>
									</td>
									<td>
										<ul>
											<li>Sem mensalidade e taxa de adesão</li>
											<li>3,79% + R$ 0,40 no crédito à vista e parcelado</li>
											<li>0,99% no PIX com recebimento em D0</li>
											<li>R$ 2,99 no boleto com recebimento em D2</li>
											<li>Antecipe o recebimento quando quiser por +2,99%</li>
										</ul>
										<button
											type="button"
											data-connect-application-id="c8672afd-abbb-4c47-a95d-7cf9cd4cee76"
											data-connect-application-environment="production"
											data-connect-nonce="<?php echo esc_attr( $nonce ); ?>"
										>
											<?php esc_html_e('Escolher este', 'pagbank-for-woocommerce'); ?>
										</button>
									</td>
								</tr>
							</tbody>
						</table>

						<div class="pagbank-connect-modal-own-condition">
							<button
								type="button"
								data-connect-application-id="f2ad0df4-4e52-4cef-97b2-4fcf1405ab9a"
								data-connect-application-environment="production"
								data-connect-nonce="<?php echo esc_attr( $nonce ); ?>"
							>
								<?php esc_html_e('Já negociei minha própria condição comercial com o PagBank', 'pagbank-for-woocommerce'); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
			<button
				name="<?php echo esc_attr( $field_key ); ?>"
				class="button button-primary"
				type="button"
				data-pagbank-connect-nonce="<?php echo esc_attr( wp_create_nonce( 'pagbank_woocommerce_oauth' ) ); ?>"
				data-pagbank-connect-environment-select="<?php echo esc_attr( $wc_settings->get_field_key( $data['environment_select'] ) ); ?>"
				data-pagbank-connected-text="<?php esc_attr_e( 'Conectar a outra conta do PagBank', 'pagbank-for-woocommerce' ); ?>"
				data-pagbank-not-connected-text="<?php esc_attr_e( 'Conectar a uma conta do PagBank', 'pagbank-for-woocommerce' ); ?>"
				data-pagbank-connect-modal-environment-id="<?php echo esc_attr( $field_key ); ?>-modal-{{environment}}"
				data-pagbank-loading-text="<?php esc_attr_e( 'Carregando...', 'pagbank-for-woocommerce' ); ?>"
				disabled
			>
				<?php esc_html_e( 'Carregando...', 'pagbank-for-woocommerce' ); ?>
			</button>
			<?php
				echo $wc_settings->get_description_html( $data );
			?>
		</fieldset>
	</td>
</tr>
