# PagBank for WooCommerce #
**Contributors:** [eliasjnior](https://profiles.wordpress.org/eliasjnior/)  
**Tags:** woocommerce, pagseguro, pagbank, pagamento, brasil  
**Requires at least:** 6.7  
**Tested up to:** 7.0  
**Requires PHP:** 7.4  
**Stable tag:** 2.0.1  
**License:** GPLv2  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Aceite pagamentos via cartão de crédito, cartão de débito, Pix, boleto e redirecionamento (Pagar com PagBank e Checkout PagBank) no WooCommerce através do PagBank.

## Description ##

O PagBank é uma empresa do grupo UOL.

Ele é pioneiro e líder no mercado brasileiro de meios de pagamento online, e possui um portfólio completo para o seu negócio.

[Clique aqui](https://pagseguro.uol.com.br/campanhas/contato/?parceiro=woocommerce) para entrar em contato com o nosso time comercial para mais informações e negociações.

### Recursos ###

Nosso módulo oferece uma integração completa com a sua loja. Receba e gerencie pagamentos com a pioneira e líder de mercado no Brasil!

Aqui estão alguns dos benefícios dessa integração:

1. Fácil instalação e configuração para integrar **pagamentos no Cartão de Crédito, Cartão de Débito, Pix, Boleto, Pagar com PagBank e Checkout PagBank** em sua loja virtual WooCommerce, proporcionando flexibilidade aos seus clientes.
2. **Compatível com Legacy Checkout e Checkout Blocks:** Todos os métodos de pagamento funcionam tanto no checkout clássico (shortcode) quanto no novo checkout em blocos do WooCommerce.
3. **Parcelamento personalizável:** Ofereça parcelamento em até 18x (mediante aprovação), com ou sem juros, permitindo que você configure essa opção de acordo com suas preferências através do plugin.
4. **Autenticação 3D Secure:** Adicione uma camada extra de segurança nas transações com cartão, transferindo a responsabilidade de fraude para o banco emissor em transações autenticadas.
5. **Reembolso fácil:** Realize reembolsos totais ou parciais diretamente na plataforma, proporcionando uma experiência satisfatória aos seus clientes.
6. **Segurança do cliente:** Dê aos seus clientes a opção de salvar seu método de pagamento, sem a necessidade de armazenar o número do cartão, garantindo a segurança de dados sensíveis.
7. **Checkout Transparente:** Permita que seus clientes façam o pagamento sem sair do seu site, proporcionando uma experiência de compra fluida e conveniente.
8. **Instruções de pagamento no pedido e e-mail:** Pix e boleto são exibidos na página do pedido e no e-mail do cliente, com código de barras escaneável para o boleto.
9. **Status de pedidos atualizados automaticamente:** Através do Webhook de retorno de dados do PagBank, os status dos pedidos são atualizados automaticamente, permitindo que você acompanhe o processo de cada transação (aprovado, negado, cancelado, etc).

### Cartão de Crédito ###

Receba e gerencie transações de cartão de crédito em sua loja.

Principais Recursos:

* Método transparente
* Autenticação 3D Secure
* Parcelamento em até 18x (mediante aprovação)
* Tokenização de cartão para compras futuras
* Reembolso online total ou parcial
* Personalização de regras para juros e parcelamento
* Informação ao cliente dos juros cobrados com atualização do total do pedido
* Consolidação de status (cancelamento e confirmação de pagamento automática)

### Cartão de Débito ###

Receba e gerencie transações de cartão de débito em sua loja.

Principais Recursos:

* Método transparente
* Autenticação 3D Secure obrigatória, transferindo a responsabilidade por fraude ao banco emissor
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

### Pix ###

Receba e gerencie transações por Pix totalmente integrado a sua conta do PagBank.

Principais Recursos:

* Método transparente
* QR code e código copia e cola exibidos na página do pedido e no e-mail do cliente
* Tempo de expiração configurável
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

**Atenção:** Para o funcionamento do PIX corretamente é necessário que você tenha uma chave Pix cadastrada na sua conta PagBank. Quer saber como cadastrar a chave PIX? [Consulte nosso artigo: Como fazer um cadastro de Chave Pix no PagBank?](https://faq.pagbank.com.br/duvida/como-cadastrar-uma-chave-pix-no-pagbank/1089).

### Boleto Bancário ###

Receba e gerencie transações por Boleto totalmente integrado a sua conta do PagBank.

Principais Recursos:

* Método transparente
* Vencimento configurável
* Exibição do boleto e do código de barras escaneável na página do pedido e no e-mail do cliente
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

### Pagar com PagBank ###

Aceite pagamentos através da carteira digital PagBank. O cliente é redirecionado para concluir a compra usando saldo da conta ou cartão de crédito pelo app PagBank.

Principais Recursos:

* Pagamento por redirecionamento
* Fluxo otimizado para celular
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

### Checkout PagBank ###

Opção "tudo em um": o cliente é redirecionado para uma página única de pagamento do PagBank que aceita cartão de crédito, Pix, boleto e saldo PagBank.

Principais Recursos:

* Pagamento por redirecionamento em página única
* Tempo de expiração configurável
* Reembolso online total ou parcial
* Consolidação de status (cancelamento e confirmação de pagamento automática)

Essa integração oferece uma série de recursos que vão facilitar e aprimorar a experiência de pagamento em sua loja virtual.

Tem alguma dúvida sobre o funcionamento ou está com algum problema técnico relacionado ao nosso plugin WooCommerce PagBank? Entre em contato com nosso [Time de integração](https://app.pipefy.com/public/form/sBlh9Nq6).

## Installation ##

### Scripts ###

Para que os métodos de pagamento tenham o correto funcionamento, durante o checkout será inicializado remotamente um Javascript externo do SDK do PagBank, que será responsável para criptografar o cartão de crédito e manter os dados dos usuários seguros.

### Requirements ###

Para instalar o PagBank for WooCommerce, você precisa:

* WordPress versão 5.4 ou superior (instalado)
* WooCommerce versão 3.9 ou superior (instalado e ativado)
* PHP versão 7.4 ou superior
* Conta no PagBank ([cadastre-se](https://cadastro.pagseguro.uol.com.br/))
* [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) instalado e ativado
* Compatível com o checkout clássico (shortcode) e com o WooCommerce Checkout Blocks

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
3. A lista de métodos de pagamento incluirá as seguintes opções: PagBank Cartão de Crédito, PagBank Cartão de Débito, PagBank Boleto, PagBank Pix, Pagar com PagBank e Checkout PagBank.
4. Clique no método de pagamento que você deseja ativar.
5. Clique no botão **Conectar ao PagBank** para associar a sua conta. Assim que você conectar ela em qualquer método de pagamento, ela conectará todos os outros métodos de pagamento na mesma conta, porém não ativará o método de pagamento automaticamente.
6. Depois que você conectar a sua conta PagBank, configure as outras opções de cada método, como parcelamento e prazo para pagamento.
7. Clique em **Salvar alterações**.

### Split de pagamento ###

O plugin disponibiliza suporte ao split de pagamento para marketplace. Para cada lojista cadastrado na sua loja, é necessário [acessar a conta](https://minhaconta.pagbank.com.br) através do navegador e navegar até *Vendas > Identificador para Marketplace*.

Com o identificador em mãos, navegue até a conta do lojista e clique no menu *Gerenciador da loja*, e depois em *Configurações > Pagamento*, deve ser preenchido o identificador da conta no campo correspondente.

Caso o lojista não possua o identificador da conta preenchido, os produtos cadastrado por esse lojista não estarão disponíveis durante o checkout.

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

### 6. Configuração do identificador do cliente no marketplace. ###
![Configuração do identificador do cliente no marketplace.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-6.png)

### 7. Histórico de pagamentos para o lojista no marketplace. ###
![Histórico de pagamentos para o lojista no marketplace.](https://raw.githubusercontent.com/pagseguro/pagbank-for-woocommerce/main/wordpress_org_assets/screenshot-7.png)


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

### 1.0.3 - 2023-08-16 ###
* Aumentado tempo de timeout para conexão com o PagBank.
* Adicionado suporte ao WooCommerce Subscriptions com cobranças automáticas.
* Corrigido pequenos bugs.

### 1.0.4 - 2023-10-26 ###
* Adicionado suporte ao WooCommerce HPOS.

### 1.0.5 - 2023-11-17 ###
* Corrigido bugs durante a instalação.

### 1.0.6 - 2023-12-04 ###
* Ajustes para aprovação do plugin pelo time do WordPress.
* Corrigido erro na geração do Pix.

### 1.0.7 - 2024-23-01 ###
* Ajustado warnings em modo de depuração do WordPress.
* Alterado título do método de pagamento dentro da visualização do pedido no dashboard.
* Adicionado mensagens de validação na configuração dos métodos de pagamento.

### 1.1.0 - 2024-24-02 ###
* Corrigido processamento de webhooks.
* Adicionado suporte para novas versões do WooCommerce e WordPress.

### 1.1.1 - 2024-28-04 ###
* Corrigido URL de webhook inválida para instalações que utilizam o WordPress em subpastas.
* Adicionado suporte a novas versão do WordPress
* Corrigido URL de API de parcelamento.

### 1.1.2 - 2024-06-06 ###
* Corrigido erro de validação para cartões American Express.

### 1.2.0 - 2024-06-10 ###
* Adicionado suporte para marketplace Dokan e WCFM.

### 1.2.1 - 2024-11-10 ###
* Adicionado suporte ao WordPress 6.7 e WooCommerce 9.3.

### 1.2.2 - 2025-01-30 ###
* Atualizado dependências de desenvolvimento.
* Corrigido erro ao atualizar o token de acesso.
* Melhoria no tratamento de webhooks.
* Adicionado alerta ao tentar conectar a conta do PagBank em ambiente de desenvolvimento.

### 1.2.3 - 2025-02-04 ###
* Corrigido problemas com CPF e CNPJ.

### 1.2.4 - 2025-02-17 ###
* Corrigido configuração para versões mais antigas do WooCommerce.
* Adicionado suporte a novas versões do WordPress e WooCommerce.
* Corrigido erro ao tentar pagar por um pedido criado manualmente.

### 1.2.5 - 2025-09-29 ###
* Atualização de segurança das bibliotecas.
* Corrigido erro ao tentar pagar um pedido através de link utilizando outro método de pagamento.

### 1.2.6 - 2025-10-12 ###
* Corrigido webhook em algumas instalações.

### 2.0.0 - 2026-03-23 ###
* Adicionado suporte ao WooCommerce Checkout Blocks para todos os métodos de pagamento.
* Adicionado método de pagamento Cartão de Débito.
* Adicionado método de pagamento "Pagar com PagBank" (redirecionamento).
* Adicionado método de pagamento "Checkout PagBank" (redirecionamento).
* Adicionado autenticação 3D Secure para pagamentos com cartão.
* Adicionado exibição do Pix e boleto na página do pedido e no e-mail do cliente.
* Adicionado código de barras escaneável nas instruções de pagamento do boleto.
* Adicionado campo de celular com validação no checkout.
* Adicionado validadores nativos de CPF e CNPJ.
* Adicionado suporte a parcelamento em até 18x (necessário aprovação).
* Adicionado página de configurações dos gateways com design moderno.
* Adicionado ícones nos métodos de pagamento no checkout.
* Melhorado sistema de logs.
* Melhorado fluxo de checkout mobile para "Pagar com PagBank".
* Melhorado tratamento de erros na conexão OAuth com PagBank.
* Melhorado acessibilidade nos campos de cartão no checkout.
* Corrigido processamento de webhook para status CANCELED.
* Corrigido agregação de splits por conta no marketplace.
* Corrigido compatibilidade com PHP 7.4.

### 2.0.1 - 2026-04-14 ###
* Corrigido textos.
* Atualizado documentação.
