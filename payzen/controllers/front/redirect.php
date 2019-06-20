<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for PrestaShop. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra-network.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

/**
 * This controller prepares form and redirects to payment gateway.
 */
class PayzenRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    private $iframe = false;
    private $logger;

    private $accepted_payment_types = array(
        'standard',
        'multi',
        'oney',
        'fullcb',
        'ancv',
        'sepa',
        'sofort',
        'paypal',
        'choozeo',
        'other',
        'grouped_other'
    );

    public function __construct()
    {
        $this->display_column_left = false;
        $this->display_column_right = version_compare(_PS_VERSION_, '1.6', '<');

        parent::__construct();

        $this->logger = PayzenTools::getLogger();
    }

    public function init()
    {
        $this->iframe = (int) Tools::getValue('content_only', 0) == 1;

        parent::init();
    }

    /**
     * Initializes page header variables
     */
    public function initHeader()
    {
        parent::initHeader();

        // to avoid document expired warning
        session_cache_limiter('private_no_expire');

        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    /**
     * PrestaShop 1.7 : override page name in template to use same styles as checkout page.
     * @return array
     */
    public function getTemplateVarPage()
    {
        if (method_exists(get_parent_class($this), 'getTemplateVarPage')) {
            $page = parent::getTemplateVarPage();
            $page['page_name'] = 'checkout';
            return $page;
        }

        return null;
    }

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        // page to redirect to if errors
        $page = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';

        // check cart errors
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() <= 0) {
            $this->payzenRedirect('index.php?controller='.$page);
        } elseif (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$this->module->active) {
            if (version_compare(_PS_VERSION_, '1.7', '<') && !Configuration::get('PS_ORDER_PROCESS_TYPE')) {
                $page .= '&step=1'; // not one page checkout, goto first checkout step
            }

            $this->payzenRedirect('index.php?controller='.$page);
        }

        $type = Tools::getValue('payzen_payment_type', null); // the selected payment submodule
        if (!$type && $this->iframe) {
            // only standard payment can be done inside iframe
            $type = 'standard';
        }

        if (!in_array($type, $this->accepted_payment_types)) {
            $this->logger->logWarning('Error: payment type "' . $type . '" is not supported. Load standard payment by default.');

            // do not log sensitive data
            $sensitive_data = array('payzen_card_number', 'payzen_cvv', 'payzen_expiry_month', 'payzen_expiry_year');
            $dataToLog = array();
            foreach ($_REQUEST as $key => $value) {
                if (in_array($key, $sensitive_data)) {
                    $dataToLog[$key] = str_repeat('*', Tools::strlen($value));
                } else {
                    $dataToLog[$key] = $value;
                }
            }

            $this->logger->logWarning('Request data : ' . print_r($dataToLog, true));

            $type = 'standard'; // force standard payment
        }

        $payment = null;
        $data = array();

        switch ($type) {
            case 'standard':
                $payment = new PayzenStandardPayment();

                if ($payment->getEntryMode() == 2 || $payment->getEntryMode() == 3) {
                    $data['card_type'] = Tools::getValue('payzen_card_type');

                    if ($payment->getEntryMode() == 3) {
                        $data['card_number'] = Tools::getValue('payzen_card_number');
                        $data['cvv'] = Tools::getValue('payzen_cvv');
                        $data['expiry_month'] = Tools::getValue('payzen_expiry_month');
                        $data['expiry_year'] = Tools::getValue('payzen_expiry_year');
                    }
                } elseif ($payment->getEntryMode() == 4) {
                    $data['iframe_mode'] = true;
                }

                break;

            case 'multi':
                $data['opt'] = Tools::getValue('payzen_opt');
                $data['card_type'] = Tools::getValue('payzen_card_type');

                $payment = new PayzenMultiPayment();
                break;

            case 'oney':
                $payment = new PayzenOneyPayment();

                $data['opt'] = Tools::getValue('payzen_oney_option');
                break;

            case 'fullcb':
                $payment = new PayzenFullcbPayment();

                $data['card_type'] = Tools::getValue('payzen_card_type');
                break;

            case 'ancv':
                $payment = new PayzenAncvPayment();
                break;

            case 'sepa':
                $payment = new PayzenSepaPayment();
                break;

            case 'sofort':
                $payment = new PayzenSofortPayment();
                break;

            case 'paypal':
                $payment = new PayzenPaypalPayment();
                break;

            case 'choozeo':
                $payment = new PayzenChoozeoPayment();
                $data['card_type'] = Tools::getValue('payzen_card_type');
                break;

            case 'other':
                $code = Tools::getValue('payzen_payment_code');
                $label = Tools::getValue('payzen_payment_label');

                $payment = new PayzenOtherPayment();
                $payment->init($code, $label);

                $data['card_type'] = $code;
                break;

            case 'grouped_other':
                $payment = new PayzenGroupedOtherPayment();

                $data['card_type'] = Tools::getValue('payzen_card_type');
                break;
        }

        // validate payment data
        $errors = $payment->validate($cart, $data);
        if (!empty($errors)) {
            $this->context->cookie->payzenPayErrors = implode("\n", $errors);

            if (version_compare(_PS_VERSION_, '1.7', '<') && !Configuration::get('PS_ORDER_PROCESS_TYPE')) {
                $page .= '&step=3';

                if (version_compare(_PS_VERSION_, '1.5.1', '<')) {
                    $page .= '&cgv=1&id_carrier='.$cart->id_carrier;
                }
            }

            $this->payzenRedirect('index.php?controller='.$page);
        }

        if (Configuration::get('PAYZEN_CART_MANAGEMENT') != PayzenTools::KEEP_CART) {
            unset($this->context->cookie->id_cart); // to avoid double call to this page
        }

        // prepare data for payment gateway form
        $request = $payment->prepareRequest($cart, $data);
        $fields = $request->getRequestFieldsArray(false, false /* data escape will be done in redirect template */);

        $dataToLog = $request->getRequestFieldsArray(true, false);
        $this->logger->logInfo('Data to be sent to payment gateway : ' . print_r($dataToLog, true));

        $this->context->smarty->assign('payzen_params', $fields);
        $this->context->smarty->assign('payzen_url', $request->get('platform_url'));
        $this->context->smarty->assign('payzen_logo', _MODULE_DIR_.'payzen/views/img/'.$payment->getLogo());
        $this->context->smarty->assign('payzen_title', $request->get('order_info'));

        if ($this->iframe) {
            $this->setTemplate(PayzenTools::getTemplatePath('iframe/redirect.tpl'));
        } else {
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                $this->setTemplate('module:payzen/views/templates/front/redirect.tpl');
            } else {
                $this->setTemplate('redirect_bc.tpl');
            }
        }
    }

    private function payzenRedirect($url)
    {
        if ($this->iframe) {
            // iframe mode, use template to redirect to top window
            $this->context->smarty->assign('payzen_url', PayzenTools::getPageLink($url));
            $this->setTemplate(PayzenTools::getTemplatePath('iframe/response.tpl'));
        } else {
            Tools::redirect($url);
        }
    }
}