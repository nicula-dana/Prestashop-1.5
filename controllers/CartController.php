<?php
/*
* 2007-2011 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 7310 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CartControllerCore extends FrontController
{
	public $php_self = 'cart';
	
	// This is not a public page, so the canonical redirection is disabled
	public function canonicalRedirection(){}
	
	public function run()
	{
		$this->init();
		$this->preProcess();

		if (Tools::getValue('ajax') == 'true')
		{
			if (Tools::getIsset('summary'))
			{
				if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 1)
				{
					if (Validate::isLoadedObject($this->context->customer))
						$groups = $this->context->customer->getGroups();
					else
						$groups = array(1);
					if ($this->context->cart->id_address_delivery)
						$deliveryAddress = new Address($this->context->cart->id_address_delivery);
					$result = array('carriers' => Carrier::getCarriersForOrder(Country::getIdZone((isset($deliveryAddress) AND (int)$deliveryAddress->id) ? (int)$deliveryAddress->id_country : (int)Configuration::get('PS_COUNTRY_DEFAULT')), $groups));
				}
				$result['summary'] = $this->context->cart->getSummaryDetails();
				$result['customizedDatas'] = Product::getAllCustomizedDatas($this->context->cart->id, null, true);
				$result['HOOK_SHOPPING_CART'] = Module::hookExec('shoppingCart', $result['summary']);
				$result['HOOK_SHOPPING_CART_EXTRA'] = Module::hookExec('shoppingCartExtra', $result['summary']);
				die(Tools::jsonEncode($result));
			}
			else
				$this->includeCartModule();
		}
		else
		{
			$this->setMedia();
			$this->displayHeader();
			$this->process();
			$this->displayContent();
			$this->displayFooter();
		}
	}

	public function includeCartModule()
	{
		require_once(_PS_MODULE_DIR_.'/blockcart/blockcart-ajax.php'); 
	}

	public function preProcess()
	{
		parent::preProcess();
		$orderTotal = $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
		$this->cartDiscounts = $this->context->cart->getDiscounts();
		foreach ($this->cartDiscounts AS $k => $this->cartDiscount)
			if ($error = $this->context->cart->checkDiscountValidity(new Discount((int)($this->cartDiscount['id_discount'])), $this->cartDiscounts, $orderTotal, $this->context->cart->getProducts(), false))
				$this->context->cart->deleteDiscount((int)($this->cartDiscount['id_discount']));

		$add = Tools::getIsset('add') ? 1 : 0;
		$delete = Tools::getIsset('delete') ? 1 : 0;

		if (Configuration::get('PS_TOKEN_ENABLE') == 1 &&
			strcasecmp(Tools::getToken(false), strval(Tools::getValue('token'))) &&
			$this->context->customer->isLogged() === true)
				$this->errors[] = Tools::displayError('Invalid token');

		// Update the cart ONLY if $this->cookies are available, in order to avoid ghost carts created by bots
		if (($add OR Tools::getIsset('update') OR $delete) AND isset($_COOKIE[$this->context->cookie->getName()]))
		{
			//get the values
			$idProduct = (int)(Tools::getValue('id_product', NULL));
			$idProductAttribute = (int)(Tools::getValue('id_product_attribute', Tools::getValue('ipa')));
			$customizationId = (int)(Tools::getValue('id_customization', 0));
			$qty = (int)(abs(Tools::getValue('qty', 1)));
			if ($qty == 0)
				$this->errors[] = Tools::displayError('Null quantity');
			elseif (!$idProduct)
				$this->errors[] = Tools::displayError('Product not found');
			else
			{
				$producToAdd = new Product($idProduct, true, $this->context->language->id);
				if ((!$producToAdd->id OR !$producToAdd->active) AND !$delete)
					if (Tools::getValue('ajax') == 'true')
						die('{"hasError" : true, "errors" : ["'.Tools::displayError('Product is no longer available.', false).'"]}');
					else
						$this->errors[] = Tools::displayError('Product is no longer available.', false);
				else
				{
					/* Check the quantity availability */
					if ($idProductAttribute AND is_numeric($idProductAttribute))
					{
						if (!$delete AND !Product::isAvailableWhenOutOfStock($producToAdd->out_of_stock) AND !Attribute::checkAttributeQty((int)$idProductAttribute, (int)$qty))
							if (Tools::getValue('ajax') == 'true')
								die('{"hasError" : true, "errors" : ["'.Tools::displayError('There is not enough product in stock.', false).'"]}');
							else
								$this->errors[] = Tools::displayError('There is not enough product in stock.');
					}
					elseif ($producToAdd->hasAttributes() AND !$delete)
					{
						$idProductAttribute = Product::getDefaultAttribute((int)$producToAdd->id, (int)$producToAdd->out_of_stock == 2 ? !(int)Configuration::get('PS_ORDER_OUT_OF_STOCK') : !(int)$producToAdd->out_of_stock);
						if (!$idProductAttribute)
							Tools::redirectAdmin($this->context->link->getProductLink($producToAdd));
						elseif (!$delete AND !Product::isAvailableWhenOutOfStock($producToAdd->out_of_stock) AND !Attribute::checkAttributeQty((int)$idProductAttribute, (int)$qty))
							if (Tools::getValue('ajax') == 'true')
								die('{"hasError" : true, "errors" : ["'.Tools::displayError('There is not enough product in stock.', false).'"]}');
							else
								$this->errors[] = Tools::displayError('There is not enough product in stock.');
					}
					elseif (!$delete AND !$producToAdd->checkQty((int)$qty))
						if (Tools::getValue('ajax') == 'true')
								die('{"hasError" : true, "errors" : ["'.Tools::displayError('There is not enough product in stock.').'"]}');
							else
								$this->errors[] = Tools::displayError('There is not enough product in stock.');
					/* Check vouchers compatibility */
					if ($add AND (($producToAdd->specificPrice AND (float)($producToAdd->specificPrice['reduction'])) OR $producToAdd->on_sale))
					{
						$discounts = $this->context->cart->getDiscounts();
						$hasUndiscountedProduct = null;
						foreach($discounts as $discount)
						{
							if(is_null($hasUndiscountedProduct))
							{
								$hasUndiscountedProduct = false;
								foreach($this->context->cart->getProducts() as $product)
									if($product['reduction_applies'] === false)
									{
										$hasUndiscountedProduct = true;
										break;
									}
							}
							if (!$discount['cumulable_reduction'] && ($discount['id_discount_type'] != Discount::PERCENT || !$hasUndiscountedProduct))
								if (Tools::getValue('ajax') == 'true')
									die('{"hasError" : true, "errors" : ["'.Tools::displayError('Cannot add this product because current voucher does not allow additional discounts.').'"]}');
								else
									$this->errors[] = Tools::displayError('Cannot add this product because current voucher does not allow additional discounts.');
							
						}
					}
					if (!sizeof($this->errors))
					{
						if ($add AND $qty >= 0)
						{
							/* Product addition to the cart */
							if (!$this->context->cart->id)
							{
								$this->context->cart->add();
								if ($this->context->cart->id)
									$this->context->cookie->id_cart = (int)$this->context->cart->id;
							}

							if ($add AND !$producToAdd->hasAllRequiredCustomizableFields() AND !$customizationId)
								$this->errors[] = Tools::displayError('Please fill in all required fields, then save the customization.');
							if (!sizeof($this->errors))
							{
								$updateQuantity = $this->context->cart->updateQty($qty, $idProduct, $idProductAttribute, $customizationId, Tools::getValue('op', 'up'));

								if ($updateQuantity < 0)
								{
									/* if product has attribute, minimal quantity is set with minimal quantity of attribute*/
									if ((int)$idProductAttribute)
										$minimal_quantity = Attribute::getAttributeMinimalQty($idProductAttribute);
									else
										$minimal_quantity = $producToAdd->minimal_quantity;
									if (Tools::getValue('ajax') == 'true')
										die('{"hasError" : true, "errors" : ["'.Tools::displayError('You must add', false).' '.$minimal_quantity.' '.Tools::displayError('Minimum quantity', false).'"]}');
									else
										$this->errors[] = Tools::displayError('You must add').' '.$minimal_quantity.' '.Tools::displayError('Minimum quantity')
										.((isset($_SERVER['HTTP_REFERER']) AND basename($_SERVER['HTTP_REFERER']) == 'order.php' OR (!Tools::isSubmit('ajax') AND substr(basename($_SERVER['REQUEST_URI']),0, strlen('cart.php')) == 'cart.php')) ? ('<script language="javascript">setTimeout("history.back()",5000);</script><br />- '.
										Tools::displayError('You will be redirected to your cart in a few seconds.')) : '');
								}
								elseif (!$updateQuantity)
								{
									if (Tools::getValue('ajax') == 'true')
										die('{"hasError" : true, "errors" : ["'.Tools::displayError('You already have the maximum quantity available for this product.', false).'"]}');
									else
										$this->errors[] = Tools::displayError('You already have the maximum quantity available for this product.')
										.((isset($_SERVER['HTTP_REFERER']) AND basename($_SERVER['HTTP_REFERER']) == 'order.php' OR (!Tools::isSubmit('ajax') AND substr(basename($_SERVER['REQUEST_URI']),0, strlen('cart.php')) == 'cart.php')) ? ('<script language="javascript">setTimeout("history.back()",5000);</script><br />- '.
										Tools::displayError('You will be redirected to your cart in a few seconds.')) : '');
								}
							}
						}
						elseif ($delete)
						{
							if ($this->context->cart->deleteProduct($idProduct, $idProductAttribute, $customizationId))
								if (!Cart::getNbProducts((int)($this->context->cart->id)))
								{
									$this->context->cart->id_carrier = 0;
									$this->context->cart->gift = 0;
									$this->context->cart->gift_message = '';
									$this->context->cart->update();
								}
						}
					}
					$discounts = $this->context->cart->getDiscounts();
					foreach($discounts AS $discount)
					{
						$discountObj = new Discount($discount['id_discount'], $this->context->language->id);

						if ($error = $this->context->cart->checkDiscountValidity($discountObj, $discounts, $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), $this->context->cart->getProducts(), false))
						{
							$this->context->cart->deleteDiscount((int)($discount['id_discount']));
							$this->context->cart->update();
							$errors[] = $error;
						}
					}
					if (!sizeof($this->errors))
					{
						$queryString = Tools::safeOutput(Tools::getValue('query', NULL));
						if ($queryString AND !Configuration::get('PS_CART_REDIRECT'))
							Tools::redirect('index.php?controller=search&search='.$queryString);
						if (isset($_SERVER['HTTP_REFERER']))
						{
							// Redirect to previous page
							preg_match('!http(s?)://(.*)/(.*)!', $_SERVER['HTTP_REFERER'], $regs);
							if (isset($regs[3]) AND !Configuration::get('PS_CART_REDIRECT') AND Tools::getValue('ajax') != 'true')
								Tools::redirect($_SERVER['HTTP_REFERER']);
						}
					}
				}
				if (Tools::getValue('ajax') != 'true' AND !sizeof($this->errors))
					Tools::redirect('index.php?controller=order&'.(isset($idProduct) ? 'ipa='.(int)($idProduct) : ''));

			}
		}
	}

	public function displayContent()
	{
		parent::displayContent();
		$this->context->smarty->display(_PS_THEME_DIR_.'errors.tpl');
	}
}
