# PagBank for WooCommerce #
**Contributors:** [eliasjnior](https://profiles.wordpress.org/eliasjnior/)  
**Tags:** woocommerce, pagseguro, pagbank, payments, ecommerce, e-commerce, store, sales, sell, shop, shopping, cart, checkout  
**Requires at least:** 5.4  
**Tested up to:** 6.3  
**Requires PHP:** 7.2  
**Stable tag:** 1.0.3  
**License:** GPLv2  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Aceite pagamentos via cartão de crédito, boleto e Pix no checkout do WooCommerce através do PagBank.

## Description ##

O PagBank é uma empresa do grupo UOL.

Ele é pioneiro e líder no mercado brasileiro de meios de pagamento online, e possui um portfólio completo para o seu negócio.

### Recursos ###

Nosso módulo oferece uma integração completa com a sua loja. Receba e gerencie pagamentos com a pioneira e líder de mercado no Brasil!

Aqui estão alguns dos benefícios dessa integração:

1. Fácil instalação e configuração para integrar **pagamentos no Cartão de Crédito, Pix e Boleto** em sua loja virtual Woocommerce, proporcionando flexibilidade aos seus clientes.
2. **Parcelamento personalizável:** Ofereça a opção de parcelamento com ou sem juros, permitindo que você configure essa opção de acordo com suas preferências através do plugin.
3. **Reembolso fácil:** Realize reembolsos totais ou parciais diretamente na plataforma, proporcionando uma experiência satisfatória aos seus clientes.
4. **Segurança do cliente:** Dê aos seus clientes a opção de salvar seu método de pagamento, sem a necessidade de armazenar o número do cartão, garantindo a segurança de dados sensíveis.
5. **Checkout Transparente:** Permita que seus clientes façam o pagamento sem sair do seu site, proporcionando uma experiência de compra fluida e conveniente.
6. **Status de pedidos atualizados automaticamente:** Através do Webhook de retorno de dados do PagSeguro, os status dos pedidos são atualizados automaticamente, permitindo que você acompanhe o processo de cada transação (aprovado, negado, cancelado, etc).

### Cartão de Crédito ###

Receba e gerencie transações de cartão de crédito em sua loja.

Principais Recursos:

* Método transparente
* Reembolso online total ou parcial
* Personalização de regras para juros e parcelamento
* Informação ao cliente dos juros cobrados com atualização do total do pedido
* Consolidação de status (cancelamento e confirmação de pagamento automática)

### Pix ###

Receba e gerencie transações por Pix totalmente integrado a sua conta do PagBank.

Principais Recursos:

* Método transparente
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

**Atenção:** Para o funcionamento do PIX corretamente é necessário que você tenha uma chave Pix cadastrada na sua conta PagBank. Quer saber como cadastrar a chave PIX? [Consulte nosso artigo: Como fazer um cadastro de Chave Pix no PagBank?](https://faq.pagseguro.uol.com.br/duvida/como-fazer-um-cadastro-de-chave-pix-no-pagbank/1089#rmcl).

### Boleto Bancário ###

Receba e gerencie transações por Boleto totalmente integrado a sua conta do PagBank.

Principais Recursos:

* Método transparente
* Reembolso online total e parcial.
* Consolidação de status (cancelamento e confirmação de pagamento automática)

Essa integração oferece uma série de recursos que vão facilitar e aprimorar a experiência de pagamento em sua loja virtual. 

Tem alguma dúvida sobre o funcionamento ou está com algum problema técnico relacionado ao nosso plugin WooCommerce PagBank? Entre em contato com nosso [Time de integração](https://app.pipefy.com/public/form/sBlh9Nq6).

## Installation ##

### Requirements ###

Para instalar o PagBank for WooCommerce, você precisa:

* WordPress versão 5.4 ou superior (instalado)
* WooCommerce versão 3.9 ou superior (instalado e ativado)
* PHP versão 7.2 ou superior
* Conta no PagBank ([cadastre-se](https://cadastro.pagseguro.uol.com.br/))
* [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) instalado e ativado

### Instructions ###

1. Faça login na administração do WordPress.
2. Vá em **Plugins > Adicionar novo**.
3. Procure pelo plugin **pagbank-for-woocommerce**.
4. Clique em **Instalar agora** e aguarde até que o plugin esteja instalado.
5. Você pode ativar o plugin imediatamente clicando em **Ativar** na página de sucesso. Se você quiser ativá-lo mais tarde, poderá fazê-lo através de **Plugins > Plugins instalados**.

### Setup and Configuration ###

Siga os passos abaixo para conectar o plugin à sua conta PagBank:

1. Após ter ativado o plugin PagBank for WooCommerce, vá em **WooCommerce > Configurações**.
2. Clique na aba **Pagamentos**.
3. A lista de métodos de pagamento incluirá três opções de pagamento: PagBank Cartão de Crédito, PagBank Boleto e PagBank Pix.
4. Clique no método de pagamento que você deseja ativar.
5. Clique no botão **Conectar ao PagBank** para associar a sua conta. Assim que você conectar ela em qualquer método de pagamento, ela conectará todos os outros métodos de pagamento na mesma conta, porém não ativará o método de pagamento automaticamente.
6. Depois que você conectar a sua conta PagBank, configure as outras opções de cada método, como parcelamento e prazo para pagamento.
7. Clique em **Salvar alterações**.

## Screenshots ##

### 1. Pagamento com cartão de crédito salvo. ###
![Pagamento com cartão de crédito salvo.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-1.png)

### 2. Pagamento com novo cartão de crédito. ###
![Pagamento com novo cartão de crédito.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-2.png)

### 3. Pagamento via Pix. ###
![Pagamento via Pix.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-3.png)

### 4. Pagamento via boleto. ###
![Pagamento via boleto.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-4.png)

### 5. Configurações de pagamentos. ###
![Configurações de pagamentos.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-5.png)


## Changelog ##

### 1.0.0 - 2023-06-26 ###
* Release inicial.

### 1.0.1 - 2023-06-27 ###
* Adicionado novos logs para tratamento de webhooks.

### 1.0.2 - 2023-07-20 ###
* Adicionado suporte ao WooCommerce 7.9.
* Adicionado obrigatoriedade do bairro durante o checkout.
* Adicionado melhorias nos logs.
* Correção de bugs durante a conexão da conta PagBank.

### 1.0.3 - 2023-08-02 ###
* Aumentado tempo de timeout para conexão com o PagBank.
