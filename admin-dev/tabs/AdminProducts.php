<?php
/*
* 2007-2011 PrestaShop
* NOTICE OF LICENSE
*
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
*  @version  Release: $Revision: 7331 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
include_once(_PS_ADMIN_DIR_.'/tabs/AdminProfiles.php');
include_once('functions.php');

class AdminProducts extends AdminTab
{
	protected $maxFileSize = 20000000;
	private $_category;

	public function __construct()
	{
		$this->table = 'product';
		$this->className = 'Product';
		$this->lang = true;
		$this->edit = true;
	 	$this->delete = true;
		$this->view = false;
		$this->duplicate = true;
		$this->imageType = 'jpg';
		$this->context = Context::getContext();

		$this->fieldsDisplay = array(
			'id_product' => array('title' => $this->l('ID'), 'align' => 'center', 'width' => 20),
			'image' => array('title' => $this->l('Photo'), 'align' => 'center', 'image' => 'p', 'width' => 45, 'orderby' => false, 'filter' => false, 'search' => false),
			'name' => array('title' => $this->l('Name'), 'width' => 220, 'filter_key' => 'b!name'),
			'reference' => array('title' => $this->l('Reference'), 'align' => 'center', 'width' => 20),
			'price' => array('title' => $this->l('Base price'), 'width' => 70, 'price' => true, 'align' => 'right', 'filter_key' => 'a!price'),
			'price_final' => array('title' => $this->l('Final price'), 'width' => 70, 'price' => true, 'align' => 'right', 'havingFilter' => true, 'orderby' => false),
			'quantity' => array('title' => $this->l('Quantity'), 'width' => 30, 'align' => 'right', 'filter_key' => 'a!quantity', 'type' => 'decimal'),
			'position' => array('title' => $this->l('Position'), 'width' => 40,'filter_key' => 'cp!position', 'align' => 'center', 'position' => 'position'),
			'a!active' => array('title' => $this->l('Displayed'), 'active' => 'status', 'filter_key' => 'a!active', 'align' => 'center', 'type' => 'bool', 'orderby' => false));

		/* Join categories table */
		$this->_category = AdminCatalog::getCurrentCategory();
		$this->_join = Product::sqlStock('a').'
			LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = a.`id_product` AND i.`cover` = 1)
			LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_product` = a.`id_product`)
			LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (a.`id_tax_rules_group` = tr.`id_tax_rules_group` AND tr.`id_country` = '.(int)$this->context->country->id.' AND tr.`id_state` = 0)
	   		LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`)';
		$this->_filter = 'AND cp.`id_category` = '.(int)($this->_category->id);
		$this->_select = 'cp.`position`, i.`id_image`, (a.`price` * ((100 + (t.`rate`))/100)) AS price_final, SUM(stock.quantity) AS quantity';

		parent::__construct();
	}
	private function _cleanMetaKeywords($keywords)
	{
		if (!empty($keywords) && $keywords != '')
		{
			$out = array();
			$words = explode(',', $keywords);
			foreach ($words as $word_item)
			{
				$word_item = trim($word_item);
				if (!empty($word_item) && $word_item != '')
					$out[] = $word_item;
			}
			return ((count($out) > 0) ? implode(',', $out) : '');
		}
		else
			return '';
	}

	protected function copyFromPost(&$object, $table)
	{
		parent::copyFromPost($object, $table);
		if (get_class($object) != 'Product')
			return;

		/* Additional fields */
		$languages = Language::getLanguages(false);
		foreach ($languages as $language)
			if (isset($_POST['meta_keywords_'.$language['id_lang']]))
			{
				$_POST['meta_keywords_'.$language['id_lang']] = $this->_cleanMetaKeywords(Tools::strtolower($_POST['meta_keywords_'.$language['id_lang']])); // preg_replace('/ *,? +,* /', ',', strtolower($_POST['meta_keywords_'.$language['id_lang']]));
				$object->meta_keywords[$language['id_lang']] = $_POST['meta_keywords_'.$language['id_lang']];
			}
		$_POST['width'] = empty($_POST['width']) ? '0' : str_replace(',', '.', $_POST['width']);
		$_POST['height'] = empty($_POST['height']) ? '0' : str_replace(',', '.', $_POST['height']);
		$_POST['depth'] = empty($_POST['depth']) ? '0' : str_replace(',', '.', $_POST['depth']);
		$_POST['weight'] = empty($_POST['weight']) ? '0' : str_replace(',', '.', $_POST['weight']);
		if ($_POST['unit_price'] != null)
			$object->unit_price = str_replace(',', '.', $_POST['unit_price']);
		if (array_key_exists('ecotax', $_POST) && $_POST['ecotax'] != null)
			$object->ecotax = str_replace(',', '.', $_POST['ecotax']);
		$object->available_for_order = (int)(Tools::isSubmit('available_for_order'));
		$object->show_price = $object->available_for_order ? 1 : (int)(Tools::isSubmit('show_price'));
		$object->on_sale = Tools::isSubmit('on_sale');
		$object->online_only = Tools::isSubmit('online_only');
	}

	public function getList($id_lang, $orderBy = null, $orderWay = null, $start = 0, $limit = null, $id_lang_shop = null)
	{
		$orderByPriceFinal = (empty($orderBy) ? ($this->context->cookie->__get($this->table.'Orderby') ? $this->context->cookie->__get($this->table.'Orderby') : 'id_'.$this->table) : $orderBy);
		$orderWayPriceFinal = (empty($orderWay) ? ($this->context->cookie->__get($this->table.'Orderway') ? $this->context->cookie->__get($this->table.'Orderby') : 'ASC') : $orderWay);
		if ($orderByPriceFinal == 'price_final')
		{
			$orderBy = 'id_'.$this->table;
			$orderWay = 'ASC';
		}
		parent::getList($id_lang, $orderBy, $orderWay, $start, $limit, $id_lang_shop);

		/* update product quantity with attributes ...*/
		$nb = count($this->_list);
		if ($this->_list)
		{
			/* update product final price */
			for ($i = 0; $i < $nb; $i++)
				$this->_list[$i]['price_tmp'] = Product::getPriceStatic($this->_list[$i]['id_product'], true, null, 6, null, false, true, 1, true);
		}

		if ($orderByPriceFinal == 'price_final')
		{
			if (strtolower($orderWayPriceFinal) == 'desc')
				uasort($this->_list, 'cmpPriceDesc');
			else
				uasort($this->_list, 'cmpPriceAsc');
		}
		for ($i = 0; $this->_list && $i < $nb; $i++)
		{
			$this->_list[$i]['price_final'] = $this->_list[$i]['price_tmp'];
			unset($this->_list[$i]['price_tmp']);
		}
	}

	public function deleteVirtualProduct()
	{
		if (!($id_product_download = ProductDownload::getIdFromIdProduct(intval(Tools::getValue('id_product')))))
			return false;
		$productDownload = new ProductDownload((int)($id_product_download));
		return $productDownload->deleteFile();
	}

	public function deleteVirtualProductAttribute()
	{
		if (!($id_product_download = ProductDownload::getIdFromIdAttibute((int) Tools::getValue('id_product_attribute'))))
			return false;
		$productDownload = new ProductDownload((int)($id_product_download));
		return $productDownload->deleteFile();
	}

	/**
	 * postProcess handle every checks before saving products information
	 *
	 * @param mixed $token
	 * @return void
	 */
	public function postProcess($token = null)
	{
		/* Add a new product */
		if (Tools::isSubmit('submitAddproduct') || Tools::isSubmit('submitAddproductAndStay') ||  Tools::isSubmit('submitAddProductAndPreview'))
		{
			if ((Tools::getValue('id_product') && $this->tabAccess['edit'] === '1') || ($this->tabAccess['add'] === '1' && !Tools::isSubmit('id_product')))
				$this->submitAddproduct($token);
			else
				$this->_errors[] = Tools::displayError('You do not have permission to add here.');
		}

		/* Delete a product in the download folder */
		if (Tools::getValue('deleteVirtualProduct'))
		{
			if ($this->tabAccess['delete'] === '1')
				$this->deleteVirtualProduct();
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}	
		
		/* Delete a product in the download folder */
		if (Tools::getValue('deleteVirtualProductAttribute'))
		{
			if ($this->tabAccess['delete'] === '1')
				$this->deleteVirtualProductAttribute();
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}

		/* Update attachments */
		else if (Tools::isSubmit('submitAddAttachments'))
		{
			if ($this->tabAccess['add'] === '1')
			{
				$languages = Language::getLanguages(false);
				$is_attachment_name_valid = false;
				foreach ($languages as $language)
				{
					$attachment_name_lang = Tools::getValue('attachment_name_'.(int)($language['id_lang']));
					if (strlen($attachment_name_lang ) > 0)
						$is_attachment_name_valid = true;

					if (!Validate::isGenericName(Tools::getValue('attachment_name_'.(int)($language['id_lang']))))
						$this->_errors[] = Tools::displayError('Invalid Name');
					else if (Tools::strlen(Tools::getValue('attachment_name_'.(int)($language['id_lang']))) > 32)
						$this->_errors[] = Tools::displayError('Name is too long');
					if (!Validate::isCleanHtml(Tools::getValue('attachment_description_'.(int)($language['id_lang']))))
						$this->_errors[] = Tools::displayError('Invalid description');
				}
				if (!$is_attachment_name_valid)
					$this->_errors[] = Tools::displayError('Attachment Name Required');

				if (empty($this->_errors))
				{
					if (isset($_FILES['attachment_file']) && is_uploaded_file($_FILES['attachment_file']['tmp_name']))
					{
						if ($_FILES['attachment_file']['size'] > (Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024 * 1024))
							$this->_errors[] = $this->l('File too large, maximum size allowed:').' '.(Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024).' '.$this->l('kb').'. '.$this->l('File size you\'re trying to upload is:').number_format(($_FILES['attachment_file']['size']/1024), 2, '.', '').$this->l('kb');
						else
						{
							do $uniqid = sha1(microtime());	while (file_exists(_PS_DOWNLOAD_DIR_.$uniqid));
							if (!copy($_FILES['attachment_file']['tmp_name'], _PS_DOWNLOAD_DIR_.$uniqid))
								$this->_errors[] = $this->l('File copy failed');
							@unlink($_FILES['attachment_file']['tmp_name']);
						}
					}
					else if ((int)$_FILES['attachment_file']['error'] === 1)
					{
						$max_upload = (int)(ini_get('upload_max_filesize'));
						$max_post = (int)(ini_get('post_max_size'));
						$upload_mb = min($max_upload, $max_post);
						$this->_errors[] = $this->l('the File').' <b>'.$_FILES['attachment_file']['name'].'</b> '.$this->l('exceeds the size allowed by the server, this limit is set to').' <b>'.$upload_mb.$this->l('Mb').'</b>';
					}

					if (empty($this->_errors) && isset($uniqid))
					{
						$attachment = new Attachment();
						foreach ($languages as $language)
						{
							if (isset($_POST['attachment_name_'.(int)($language['id_lang'])]))
								$attachment->name[(int)($language['id_lang'])] = pSQL($_POST['attachment_name_'.(int)($language['id_lang'])]);
							if (isset($_POST['attachment_description_'.(int)($language['id_lang'])]))
								$attachment->description[(int)($language['id_lang'])] = pSQL($_POST['attachment_description_'.(int)($language['id_lang'])]);
						}
						$attachment->file = $uniqid;
						$attachment->mime = $_FILES['attachment_file']['type'];
						$attachment->file_name = pSQL($_FILES['attachment_file']['name']);
						if (empty($attachment->mime) || Tools::strlen($attachment->mime) > 128)
							$this->_errors[] = Tools::displayError('Invalid file extension');
						if (!Validate::isGenericName($attachment->file_name))
							$this->_errors[] = Tools::displayError('Invalid file name');
						if (Tools::strlen($attachment->file_name) > 128)
							$this->_errors[] = Tools::displayError('File name too long');
						if (!sizeof($this->_errors))
						{
							$attachment->add();
							Tools::redirectAdmin(self::$currentIndex.'&id_product='.(int)(Tools::getValue($this->identifier)).'&id_category='.(int)(Tools::getValue('id_category')).'&addproduct&conf=4&tabs=6&token='.($token ? $token : $this->token));
						}
						else
							$this->_errors[] = Tools::displayError('Invalid file');
					}
				}
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to add here.');
		}
		else if (Tools::isSubmit('submitAttachments'))
		{
			if ($this->tabAccess['edit'] === '1')
				if ($id = (int)(Tools::getValue($this->identifier)))
					if (Attachment::attachToProduct($id, Tools::getValue('attachments')))
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.(int)$id.(isset($_POST['id_category']) ? '&id_category='.(int)$_POST['id_category'] : '').'&conf=4&add'.$this->table.'&tabs=6&token='.($token ? $token : $this->token));
		}

		/* Product duplication */
		else if (isset($_GET['duplicate'.$this->table]))
		{
			if ($this->tabAccess['add'] === '1')
			{
				if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
				{
					$id_product_old = $product->id;
					unset($product->id);
					unset($product->id_product);
					$product->indexed = 0;
					$product->active = 0;
					if ($product->add()
					&& Category::duplicateProductCategories($id_product_old, $product->id)
					&& ($combinationImages = Product::duplicateAttributes($id_product_old, $product->id)) !== false
					&& GroupReduction::duplicateReduction($id_product_old, $product->id)
					&& Product::duplicateAccessories($id_product_old, $product->id)
					&& Product::duplicateFeatures($id_product_old, $product->id)
					&& Product::duplicateSpecificPrices($id_product_old, $product->id)
					&& Pack::duplicate($id_product_old, $product->id)
					&& Product::duplicateCustomizationFields($id_product_old, $product->id)
					&& Product::duplicateTags($id_product_old, $product->id)
					&& Product::duplicateDownload($id_product_old, $product->id)
					&& $product->duplicateShops($id_product_old))
					{
						if ($product->hasAttributes())
							Product::updateDefaultAttribute($product->id);

						if (!Tools::getValue('noimage') && !Image::duplicateProductImages($id_product_old, $product->id, $combinationImages))
							$this->_errors[] = Tools::displayError('An error occurred while copying images.');
						else
						{
							Hook::addProduct($product);
							Search::indexation(false, $product->id);
							Tools::redirectAdmin(self::$currentIndex.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&conf=19&token='.($token ? $token : $this->token));
						}
					}
					else
						$this->_errors[] = Tools::displayError('An error occurred while creating object.');
				}
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to add here.');
		}
		/* Change object statuts (active, inactive) */
		else if (isset($_GET['status']) && Tools::getValue($this->identifier))
		{
			if ($this->tabAccess['edit'] === '1')
			{
				if (Validate::isLoadedObject($object = $this->loadObject()))
				{
					if ($object->toggleStatus())
						Tools::redirectAdmin(self::$currentIndex.'&conf=5'.((($id_category = (!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1')) && Tools::getValue('id_product')) ? '&id_category='.$id_category : '').'&token='.$token);
					else
						$this->_errors[] = Tools::displayError('An error occurred while updating status.');
				}
				else
					$this->_errors[] = Tools::displayError('An error occurred while updating status for object.').' <b>'.$this->table.'</b> '.Tools::displayError('(cannot load object)');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}
		/* Delete object */
		else if (isset($_GET['delete'.$this->table]))
		{
			if ($this->tabAccess['delete'] === '1')
			{
				if (Validate::isLoadedObject($object = $this->loadObject()) && isset($this->fieldImageSettings))
				{
					// check if request at least one object with noZeroObject
					if (isset($object->noZeroObject) && sizeof($taxes = call_user_func(array($this->className, $object->noZeroObject))) <= 1)
						$this->_errors[] = Tools::displayError('You need at least one object.').' <b>'.$this->table.'</b><br />'.Tools::displayError('You cannot delete all of the items.');
					else
					{
						$id_category = Tools::getValue('id_category');
						$category_url = empty($id_category) ? '' : '&id_category='.$id_category;

						if ($this->deleted)
						{
							$object->deleteImages();
							$object->deleted = 1;
							if ($object->update())
								Tools::redirectAdmin(self::$currentIndex.'&conf=1&token='.($token ? $token : $this->token).$category_url);
						}
						else if ($object->delete())
							Tools::redirectAdmin(self::$currentIndex.'&conf=1&token='.($token ? $token : $this->token).$category_url);
						$this->_errors[] = Tools::displayError('An error occurred during deletion.');
					}
				}
				else
					$this->_errors[] = Tools::displayError('An error occurred while deleting object.').' <b>'.$this->table.'</b> '.Tools::displayError('(cannot load object)');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}

		/* Delete multiple objects */
		else if (Tools::getValue('submitDel'.$this->table))
		{
			if ($this->tabAccess['delete'] === '1')
			{
				if (isset($_POST[$this->table.'Box']))
				{
					$object = new $this->className();

					if (isset($object->noZeroObject) &&
						// Check if all object will be deleted
						(sizeof(call_user_func(array($this->className, $object->noZeroObject))) <= 1 || sizeof($_POST[$this->table.'Box']) == sizeof(call_user_func(array($this->className, $object->noZeroObject)))))
						$this->_errors[] = Tools::displayError('You need at least one object.').' <b>'.$this->table.'</b><br />'.Tools::displayError('You cannot delete all of the items.');
					else
					{
						$result = true;
						if ($this->deleted)
						{
							foreach (Tools::getValue($this->table.'Box') as $id)
							{
								$toDelete = new $this->className($id);
								$toDelete->deleted = 1;
								$result = $result && $toDelete->update();
							}
						}
						else
							$result = $object->deleteSelection(Tools::getValue($this->table.'Box'));

						if ($result)
						{
							$id_category = Tools::getValue('id_category');
							$category_url = empty($id_category) ? '' : '&id_category='.$id_category;

							Tools::redirectAdmin(self::$currentIndex.'&conf=2&token='.$token.$category_url);
						}
						$this->_errors[] = Tools::displayError('An error occurred while deleting selection.');
					}
				}
				else
					$this->_errors[] = Tools::displayError('You must select at least one element to delete.');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}

		/* Product images management */
		else if (($id_image = (int)(Tools::getValue('id_image'))) && Validate::isUnsignedId($id_image) && Validate::isLoadedObject($image = new Image($id_image)))
		{
			if ($this->tabAccess['edit'] === '1')
			{
				/* Update product image/legend */
				if (isset($_GET['editImage']))
				{
					if ($image->cover)
						$_POST['cover'] = 1;
					$languages = Language::getLanguages(false);
					foreach ($languages as $language)
						if (isset($image->legend[$language['id_lang']]))
							$_POST['legend_'.$language['id_lang']] = $image->legend[$language['id_lang']];
					$_POST['id_image'] = $image->id;
					$this->displayForm();
				}

				/* Choose product cover image */
				else if (isset($_GET['coverImage']))
				{
					Image::deleteCover($image->id_product);
					$image->cover = 1;
					if (!$image->update())
						$this->_errors[] = Tools::displayError('Cannot change the product cover');
					else
					{
						$productId = (int)(Tools::getValue('id_product'));
						@unlink(_PS_TMP_IMG_DIR_.'/product_'.$productId.'.jpg');
						@unlink(_PS_TMP_IMG_DIR_.'/product_mini_'.$productId.'.jpg');
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.$image->id_product.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&addproduct&tabs=1'.'&token='.($token ? $token : $this->token));
					}
				}

				/* Choose product image position */
				else if (isset($_GET['imgPosition']) && isset($_GET['imgDirection']))
				{
					$image->updatePosition(Tools::getValue('imgDirection'), Tools::getValue('imgPosition'));
					Tools::redirectAdmin(self::$currentIndex.'&id_product='.$image->id_product.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&add'.$this->table.'&tabs=1&token='.($token ? $token : $this->token));
				}
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}

		/* Product attributes management */
		else if (Tools::isSubmit('submitProductAttribute'))
		{
			if (!Combination::isFeatureActive())
				return;
			if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
			{
				if (!isset($_POST['attribute_price']) || $_POST['attribute_price'] == null)
					$this->_errors[] = Tools::displayError('Attribute price required.');
				if (!isset($_POST['attribute_combinaison_list']) || !sizeof($_POST['attribute_combinaison_list']))
					$this->_errors[] = Tools::displayError('You must add at least one attribute.');

				if (!sizeof($this->_errors))
				{
					if (!isset($_POST['attribute_wholesale_price'])) $_POST['attribute_wholesale_price'] = 0;
					if (!isset($_POST['attribute_price_impact'])) $_POST['attribute_price_impact'] = 0;
					if (!isset($_POST['attribute_weight_impact'])) $_POST['attribute_weight_impact'] = 0;
					if (!isset($_POST['attribute_ecotax'])) $_POST['attribute_ecotax'] = 0;
					if (Tools::getValue('attribute_default'))
						$product->deleteDefaultAttributes();
					// Change existing one
					if ($id_product_attribute = (int)(Tools::getValue('id_product_attribute')))
					{
						if ($this->tabAccess['edit'] === '1')
						{
							if ($product->productAttributeExists($_POST['attribute_combinaison_list'], $id_product_attribute))
								$this->_errors[] = Tools::displayError('This attribute already exists.');
							else
							{
								if (Validate::isDateFormat(Tools::getValue('available_date')))
								{
									$product->updateProductAttribute($id_product_attribute,
										Tools::getValue('attribute_wholesale_price'),
										Tools::getValue('attribute_price') * Tools::getValue('attribute_price_impact'),
										Tools::getValue('attribute_weight') * Tools::getValue('attribute_weight_impact'),
										Tools::getValue('attribute_unity') * Tools::getValue('attribute_unit_impact'),
										Tools::getValue('attribute_ecotax'),
										false,
										Tools::getValue('id_image_attr'),
										Tools::getValue('attribute_reference'),
										Tools::getValue('attribute_supplier_reference'),
										Tools::getValue('attribute_ean13'),
										Tools::getValue('attribute_default'),
										Tools::getValue('attribute_location'),
										Tools::getValue('attribute_upc'),
										Tools::getValue('minimal_quantity'),
										Tools::getValue('available_date'));

									if ($id_reason = (int)Tools::getValue('id_mvt_reason') && (int)Tools::getValue('attribute_mvt_quantity') > 0 && $id_reason > 0)
									{
										if (!$product->addStockMvt(Tools::getValue('attribute_mvt_quantity'), $id_reason, $id_product_attribute, null, $this->context->employee->id))
											$this->_errors[] = Tools::displayError('An error occurred while updating qty.');
									}
									Hook::updateProductAttribute((int)$id_product_attribute);
									$this->updateDownloadProduct($product, 1, $id_product_attribute);
								}
								else
								{
									$this->_errors[] = Tools::displayError('Invalid date format.');
								}
							}
						}
						else
							$this->_errors[] = Tools::displayError('You do not have permission to add here.');
					}
					// Add new
					else
					{
						if ($this->tabAccess['add'] === '1')
						{
							if ($product->productAttributeExists($_POST['attribute_combinaison_list']))
								$this->_errors[] = Tools::displayError('This combination already exists.');
							else
								$id_product_attribute = $product->addCombinationEntity(
									Tools::getValue('attribute_wholesale_price'),
									Tools::getValue('attribute_price') * Tools::getValue('attribute_price_impact'),
									Tools::getValue('attribute_weight') * Tools::getValue('attribute_weight_impact'),
									Tools::getValue('attribute_unity') * Tools::getValue('attribute_unit_impact'),
                                	Tools::getValue('attribute_ecotax'),
                                	Tools::getValue('attribute_quantity'),
                                	Tools::getValue('id_image_attr'),
                                	Tools::getValue('attribute_reference'),
                                	Tools::getValue('attribute_supplier_reference'),
                                	Tools::getValue('attribute_ean13'),
                                	Tools::getValue('attribute_default'),
                                	Tools::getValue('attribute_location'),
                                	Tools::getValue('attribute_upc'));
							$this->updateDownloadProduct($product, 0, $id_product_attribute);
						}
						else
							$this->_errors[] = Tools::displayError('You do not have permission to').'<hr>'.Tools::displayError('Edit here.');
					}
					if (!sizeof($this->_errors))
					{
						$product->addAttributeCombinaison($id_product_attribute, Tools::getValue('attribute_combinaison_list'));
						$product->checkDefaultAttributes();
					}
					if (!sizeof($this->_errors))
					{
						if (!$product->cache_default_attribute)
							Product::updateDefaultAttribute($product->id);
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.$product->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&add'.$this->table.'&tabs=3&token='.($token ? $token : $this->token));
					}	
				}
			}
		}
		else if (Tools::isSubmit('deleteProductAttribute'))
		{
			if (!Combination::isFeatureActive())
				return;
			if ($this->tabAccess['delete'] === '1')
			{
				if (($id_product = (int)(Tools::getValue('id_product'))) && Validate::isUnsignedId($id_product) && Validate::isLoadedObject($product = new Product($id_product)))
				{
					$product->deleteAttributeCombinaison(Tools::getValue('id_product_attribute'));
					
					$id_product_download = ProductDownload::getIdFromIdAttibute((int) Tools::getValue('id_product_attribute'));
					if ($id_product_download)
					{
						$productDownload = new ProductDownload((int) $id_product_download);
						$this->deleteDownloadProduct((int) $id_product_download);
						$productDownload->deleteFile();
					}
					$product->checkDefaultAttributes();
					$product->updateQuantityProductWithAttributeQuantity();
					if (!$product->hasAttributes())
					{
						$product->cache_default_attribute = 0;
						$product->update();
					}
					else
						Product::updateDefaultAttribute($id_product);

					Tools::redirectAdmin(self::$currentIndex.'&add'.$this->table.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&tabs=3&id_product='.$product->id.'&token='.($token ? $token : $this->token));
				}
				else
					$this->_errors[] = Tools::displayError('Cannot delete attribute');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}
		else if (Tools::isSubmit('deleteAllProductAttributes'))
		{
			if (!Combination::isFeatureActive())
				return;
			if ($this->tabAccess['delete'] === '1')
			{
				if (($id_product = (int)(Tools::getValue('id_product'))) && Validate::isUnsignedId($id_product) && Validate::isLoadedObject($product = new Product($id_product)))
				{
					$product->deleteProductAttributes();
					$product->updateQuantityProductWithAttributeQuantity();
					if ($product->cache_default_attribute)
					{
						$product->cache_default_attribute = 0;
						$product->update();
					}
					Tools::redirectAdmin(self::$currentIndex.'&add'.$this->table.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&tabs=3&id_product='.$product->id.'&token='.($token ? $token : $this->token));
				}
				else
					$this->_errors[] = Tools::displayError('Cannot delete attributes');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}
		else if (Tools::isSubmit('defaultProductAttribute'))
		{
			if (!Combination::isFeatureActive())
				return;
			if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
			{
				$product->deleteDefaultAttributes();
				$product->setDefaultAttribute((int)(Tools::getValue('id_product_attribute')));
				Tools::redirectAdmin(self::$currentIndex.'&add'.$this->table.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&tabs=3&id_product='.$product->id.'&token='.($token ? $token : $this->token));
			}
			else
				$this->_errors[] = Tools::displayError('Cannot make default attribute');
		}

		/* Product features management */
		else if (Tools::isSubmit('submitProductFeature'))
		{
			if (!Feature::isFeatureActive())
				return;
			if ($this->tabAccess['edit'] === '1')
			{
				if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
				{
					// delete all objects
					$product->deleteFeatures();

					// add new objects
					$languages = Language::getLanguages(false);
					foreach ($_POST as $key => $val)
					{
						if (preg_match('/^feature_([0-9]+)_value/i', $key, $match))
						{
							if ($val)
								$product->addFeaturesToDB($match[1], $val);
							else
							{
								if ($default_value = $this->checkFeatures($languages, $match[1]))
								{
									$id_value = $product->addFeaturesToDB($match[1], 0, 1, (int)$language['id_lang']);
									foreach ($languages as $language)
									{
										if ($cust = Tools::getValue('custom_'.$match[1].'_'.(int)$language['id_lang']))
											$product->addFeaturesCustomToDB($id_value, (int)$language['id_lang'], $cust);
										else
											$product->addFeaturesCustomToDB($id_value, (int)$language['id_lang'], $default_value);
									}
								}
							}
						}
					}
					if (!sizeof($this->_errors))
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.(int)$product->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&add'.$this->table.'&tabs=4&conf=4&token='.($token ? $token : $this->token));
				}
				else
					$this->_errors[] = Tools::displayError('Product must be created before adding features.');
			}
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}
		/* Product specific prices management */
		else if (Tools::isSubmit('submitPricesModification'))
		{
			$_POST['tabs'] = 5;
			if ($this->tabAccess['edit'] === '1')
			{
				$id_specific_prices = Tools::getValue('spm_id_specific_price');
				$id_shops = Tools::getValue('spm_id_shop');
				$id_currencies = Tools::getValue('spm_id_currency');
				$id_countries = Tools::getValue('spm_id_country');
				$id_groups = Tools::getValue('spm_id_group');
				$prices = Tools::getValue('spm_price');
				$from_quantities = Tools::getValue('spm_from_quantity');
				$reductions = Tools::getValue('spm_reduction');
				$reduction_types = Tools::getValue('spm_reduction_type');
				$froms = Tools::getValue('spm_from');
				$tos = Tools::getValue('spm_to');

				foreach ($id_specific_prices as $key => $id_specific_price)
					if ($this->_validateSpecificPrice($id_shops[$key], $id_currencies[$key], $id_countries[$key], $id_groups[$key], $prices[$key], $from_quantities[$key], $reductions[$key], $reduction_types[$key], $froms[$key], $tos[$key]))
					{
						$specificPrice = new SpecificPrice((int)($id_specific_price));
						$specificPrice->id_shop = (int)$id_shops[$key];
						$specificPrice->id_currency = (int)($id_currencies[$key]);
						$specificPrice->id_country = (int)($id_countries[$key]);
						$specificPrice->id_group = (int)($id_groups[$key]);
						$specificPrice->price = (float)($prices[$key]);
						$specificPrice->from_quantity = (int)($from_quantities[$key]);
						$specificPrice->reduction = (float)($reduction_types[$key] == 'percentage' ? ($reductions[$key] / 100) : $reductions[$key]);
						$specificPrice->reduction_type = !$reductions[$key] ? 'amount' : $reduction_types[$key];
						$specificPrice->from = !$froms[$key] ? '0000-00-00 00:00:00' : $froms[$key];
						$specificPrice->to = !$tos[$key] ? '0000-00-00 00:00:00' : $tos[$key];
						if (!$specificPrice->update())
							$this->_errors = Tools::displayError('An error occurred while updating the specific price.');
					}
				if (!sizeof($this->_errors))
					Tools::redirectAdmin(self::$currentIndex.'&id_product='.(int)(Tools::getValue('id_product')).'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&update'.$this->table.'&tabs=2&token='.($token ? $token : $this->token));
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to add here.');
		}
		else if (Tools::isSubmit('submitPriceAddition'))
		{
			if ($this->tabAccess['add'] === '1')
			{
				$id_product = (int)(Tools::getValue('id_product'));
				$id_shop = Tools::getValue('sp_id_shop');
				$id_currency = Tools::getValue('sp_id_currency');
				$id_country = Tools::getValue('sp_id_country');
				$id_group = Tools::getValue('sp_id_group');
				$price = Tools::getValue('sp_price');
				$from_quantity = Tools::getValue('sp_from_quantity');
				$reduction = (float)(Tools::getValue('sp_reduction'));
				$reduction_type = !$reduction ? 'amount' : Tools::getValue('sp_reduction_type');
				$from = Tools::getValue('sp_from');
				$to = Tools::getValue('sp_to');
				if ($this->_validateSpecificPrice($id_shop, $id_currency, $id_country, $id_group, $price, $from_quantity, $reduction, $reduction_type, $from, $to))
				{
					$specificPrice = new SpecificPrice();
					$specificPrice->id_product = $id_product;
					$specificPrice->id_shop = (int)$id_shop;
					$specificPrice->id_currency = (int)($id_currency);
					$specificPrice->id_country = (int)($id_country);
					$specificPrice->id_group = (int)($id_group);
					$specificPrice->price = (float)($price);
					$specificPrice->from_quantity = (int)($from_quantity);
					$specificPrice->reduction = (float)($reduction_type == 'percentage' ? $reduction / 100 : $reduction);
					$specificPrice->reduction_type = $reduction_type;
					$specificPrice->from = !$from ? '0000-00-00 00:00:00' : $from;
					$specificPrice->to = !$to ? '0000-00-00 00:00:00' : $to;
					if (!$specificPrice->add())
						$this->_errors = Tools::displayError('An error occurred while updating the specific price.');
					else
						Tools::redirectAdmin(self::$currentIndex.(Tools::getValue('id_category') ? '&id_category='.Tools::getValue('id_category') : '').'&id_product='.$id_product.'&add'.$this->table.'&tabs=2&conf=3&token='.($token ? $token : $this->token));
				}
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to add here.');
		}
		else if (Tools::isSubmit('deleteSpecificPrice'))
		{
			if ($this->tabAccess['delete'] === '1')
			{
				if (!($obj = $this->loadObject()))
					return;
				if (!$id_specific_price = Tools::getValue('id_specific_price') || !Validate::isUnsignedId($id_specific_price))
					$this->_errors[] = Tools::displayError('Invalid specific price ID');
				else
				{
					$specificPrice = new SpecificPrice((int)($id_specific_price));
					if (!$specificPrice->delete())
						$this->_errors[] = Tools::displayError('An error occurred while deleting the specific price');
					else
						Tools::redirectAdmin(self::$currentIndex.(Tools::getValue('id_category') ? '&id_category='.Tools::getValue('id_category') : '').'&id_product='.$obj->id.'&add'.$this->table.'&tabs=2&conf=1&token='.($token ? $token : $this->token));
				}
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to delete here.');
		}
		else if (Tools::isSubmit('submitSpecificPricePriorities'))
		{
			if (!($obj = $this->loadObject()))
				return;
			if (!$priorities = Tools::getValue('specificPricePriority'))
				$this->_errors[] = Tools::displayError('Please specify priorities');
			else if (Tools::isSubmit('specificPricePriorityToAll'))
			{
				if (!SpecificPrice::setPriorities($priorities))
					$this->_errors[] = Tools::displayError('An error occurred while updating priorities.');
				else
					Tools::redirectAdmin(self::$currentIndex.'&id_product='.$obj->id.'&add'.$this->table.'&tabs=2&conf=4&token='.($token ? $token : $this->token));
			}
			else if (!SpecificPrice::setSpecificPriority((int)($obj->id), $priorities))
				$this->_errors[] = Tools::displayError('An error occurred while setting priorities.');
			else
				Tools::redirectAdmin(self::$currentIndex.(Tools::getValue('id_category') ? '&id_category='.Tools::getValue('id_category') : '').'&id_product='.$obj->id.'&add'.$this->table.'&tabs=2&conf=4&token='.($token ? $token : $this->token));
		}
		/* Customization management */
		else if (Tools::isSubmit('submitCustomizationConfiguration'))
		{
			if ($this->tabAccess['edit'] === '1')
			{
				if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
				{
					if (!$product->createLabels((int)($_POST['uploadable_files']) - (int)($product->uploadable_files), (int)($_POST['text_fields']) - (int)($product->text_fields)))
						$this->_errors[] = Tools::displayError('An error occurred while creating customization fields.');
					if (!sizeof($this->_errors) && !$product->updateLabels())
						$this->_errors[] = Tools::displayError('An error occurred while updating customization.');
					$product->uploadable_files = (int)($_POST['uploadable_files']);
					$product->text_fields = (int)($_POST['text_fields']);
					$product->customizable = ((int)($_POST['uploadable_files']) > 0 || (int)($_POST['text_fields']) > 0) ? 1 : 0;
					if (!sizeof($this->_errors) && !$product->update())
						$this->_errors[] = Tools::displayError('An error occurred while updating customization configuration.');
					if (!sizeof($this->_errors))
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.$product->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&add'.$this->table.'&tabs=5&token='.($token ? $token : $this->token));
				}
				else
					$this->_errors[] = Tools::displayError('Product must be created before adding customization possibilities.');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}
		else if (Tools::isSubmit('submitProductCustomization'))
		{
			if ($this->tabAccess['edit'] === '1')
			{
				if (Validate::isLoadedObject($product = new Product((int)(Tools::getValue('id_product')))))
				{
					foreach ($_POST as $field => $value)
						if (strncmp($field, 'label_', 6) == 0 && !Validate::isLabel($value))
							$this->_errors[] = Tools::displayError('Label fields are invalid');
					if (!sizeof($this->_errors) && !$product->updateLabels())
						$this->_errors[] = Tools::displayError('An error occurred while updating customization.');
					if (!sizeof($this->_errors))
						Tools::redirectAdmin(self::$currentIndex.'&id_product='.$product->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&add'.$this->table.'&tabs=5&token='.($token ? $token : $this->token));
				}
				else
					$this->_errors[] = Tools::displayError('Product must be created before adding customization possibilities.');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}
		else if (isset($_GET['position']))
		{
			if ($this->tabAccess['edit'] !== '1')
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
			else if (!Validate::isLoadedObject($object = $this->loadObject()))
				$this->_errors[] = Tools::displayError('An error occurred while updating status for object.').' <b>'.$this->table.'</b> '.Tools::displayError('(cannot load object)');
			if (!$object->updatePosition((int)(Tools::getValue('way')), (int)(Tools::getValue('position'))))
				$this->_errors[] = Tools::displayError('Failed to update the position.');
			else
				Tools::redirectAdmin(self::$currentIndex.'&'.$this->table.'Orderby=position&'.$this->table.'Orderway=asc&conf=5'.(($id_category = (!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1')) ? ('&id_category='.$id_category) : '').'&token='.Tools::getAdminTokenLite('AdminCatalog'));
		}
		else
			parent::postProcess(true);
	}

	public function ajaxProcess()
	{
		if (Tools::getValue('addImage') !== false)
		{
			self::$currentIndex = 'index.php?tab=AdminCatalog';
			$allowedExtensions = array("jpeg", "gif", "png", "jpg");
			// max file size in bytes
			$sizeLimit = $this->maxFileSize;
			$uploader = new FileUploader($allowedExtensions, $sizeLimit);
			$result = $uploader->handleUpload();
			if (isset($result['success']))
			{
				$shops = false;
				if (Shop::isMultiShopActivated())
					$shops = Shop::getShops();
				$obj = new Product((int)Tools::getValue('id_product'));
				$countImages = (int)Db::getInstance()->getValue('SELECT COUNT(id_product) FROM '._DB_PREFIX_.'image  WHERE id_product = '.(int)$obj->id);
				$images = Image::getImages($this->context->language->id, $obj->id);
				$imagesTotal = Image::getImagesTotal($obj->id);
				$html = $this->getLineTableImage($result['success'], $imagesTotal + 1, $this->token, $shops);
				die(Tools::jsonEncode(array("success" => $html)));
			}
			else
				die(Tools::jsonEncode($result));
		}

		if (Tools::getValue('updateProductImageShopAsso'))
		{
			if ($id_image = (int)Tools::getValue('id_image') && $id_shop = (int)Tools::getValue('id_shop'))
				if (Tools::getValue('active') == "true")
					die(Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'image_shop (`id_image`, `id_shop`) VALUES('.(int)$id_image.', '.(int)$id_shop.')'));
				else
					die(Db::getInstance()->Execute('DELETE FROM '._DB_PREFIX_.'image_shop WHERE `id_image`='.(int)$id_image.' && `id_shop`='.(int)$id_shop));
		}

		if (Tools::getValue('deleteImage'))
		{
			$image = new Image((int)Tools::getValue('id_image'));
			$image->delete();
			if (!Image::getCover($image->id_product))
			{
				$first_img = Db::getInstance()->getRow('
				SELECT `id_image` FROM `'._DB_PREFIX_.'image`
				WHERE `id_product` = '.(int)($image->id_product));
				Db::getInstance()->Execute('
				UPDATE `'._DB_PREFIX_.'image`
				SET `cover` = 1
				WHERE `id_image` = '.(int)($first_img['id_image']));
			}
			@unlink(_PS_TMP_IMG_DIR_.'/product_'.$image->id_product.'.jpg');
			@unlink(_PS_TMP_IMG_DIR_.'/product_mini_'.$image->id_product.'.jpg');
			die(true);
		}

	}
	protected function _validateSpecificPrice($id_shop, $id_currency, $id_country, $id_group, $price, $from_quantity, $reduction, $reduction_type, $from, $to)
	{
		if (!Validate::isUnsignedId($id_shop) || !Validate::isUnsignedId($id_currency) || !Validate::isUnsignedId($id_country) || !Validate::isUnsignedId($id_group))
			$this->_errors[] = Tools::displayError('Wrong ID\'s');
		else if ((empty($price) && empty($reduction)) || (!empty($price) && !Validate::isPrice($price)) || (!empty($reduction) && !Validate::isPrice($reduction)))
			$this->_errors[] = Tools::displayError('Invalid price/reduction amount');
		else if (!Validate::isUnsignedInt($from_quantity))
			$this->_errors[] = Tools::displayError('Invalid quantity');
		else if ($reduction && !Validate::isReductionType($reduction_type))
			$this->_errors[] = Tools::displayError('Please select a reduction type (amount or percentage)');
		else if ($from && $to && (!Validate::isDateFormat($from) || !Validate::isDateFormat($to)))
			$this->_errors[] = Tools::displayError('Wrong from/to date');
		else
			return true;
		return false;
	}

	// Checking customs feature
	private function checkFeatures($languages, $feature_id)
	{
		$rules = call_user_func(array('FeatureValue', 'getValidationRules'), 'FeatureValue');
		$feature = Feature::getFeature(Configuration::get('PS_LANG_DEFAULT'), $feature_id);
		$val = 0;
		foreach ($languages as $language)
			if ($val = Tools::getValue('custom_'.$feature_id.'_'.$language['id_lang']))
			{
				$currentLanguage = new Language($language['id_lang']);
				if (Tools::strlen($val) > $rules['sizeLang']['value'])
					$this->_errors[] = Tools::displayError('name for feature').' <b>'.$feature['name'].'</b> '.Tools::displayError('is too long in').' '.$currentLanguage->name;
				else if (!call_user_func(array('Validate', $rules['validateLang']['value']), $val))
					$this->_errors[] = Tools::displayError('Valid name required for feature.').' <b>'.$feature['name'].'</b> '.Tools::displayError('in').' '.$currentLanguage->name;
				if (sizeof($this->_errors))
					return (0);
				// Getting default language
				if ($language['id_lang'] == Configuration::get('PS_LANG_DEFAULT'))
					return ($val);
			}
		return (0);
	}


	/**
	 * Add or update a product image
	 *
	 * @param object $product Product object to add image
	 */
	public function addProductImage($product, $method = 'auto')
	{
		/* Updating an existing product image */
		if ($id_image = ((int)(Tools::getValue('id_image'))))
		{
			$image = new Image($id_image);
			if (!Validate::isLoadedObject($image))
				$this->_errors[] = Tools::displayError('An error occurred while loading object image.');
			else
			{
				if (($cover = Tools::getValue('cover')) == 1)
					Image::deleteCover($product->id);
				$image->cover = $cover;
				$this->validateRules('Image');
				$this->copyFromPost($image, 'image');
				if (sizeof($this->_errors) || !$image->update())
					$this->_errors[] = Tools::displayError('An error occurred while updating image.');
				else if (isset($_FILES['image_product']['tmp_name']) && $_FILES['image_product']['tmp_name'] != null)
					$this->copyImage($product->id, $image->id, $method);
			}
		}
		if (isset($image) && Validate::isLoadedObject($image) && !file_exists(_PS_PROD_IMG_DIR_.$image->getExistingImgPath().'.'.$image->image_format))
			$image->delete();
		if (sizeof($this->_errors))
			return false;
		@unlink(_PS_TMP_IMG_DIR_.'/product_'.$product->id.'.jpg');
		@unlink(_PS_TMP_IMG_DIR_.'/product_mini_'.$product->id.'.jpg');
		return ((isset($id_image) && is_int($id_image) && $id_image) ? $id_image : true);
	}
	/**
	 * Copy a product image
	 *
	 * @param integer $id_product Product Id for product image filename
	 * @param integer $id_image Image Id for product image filename
	 */
	public function copyImage($id_product, $id_image, $method = 'auto')
	{
		if (!isset($_FILES['image_product']['tmp_name']))
			return false;
		if ($error = checkImage($_FILES['image_product']))
			$this->_errors[] = $error;
		else
		{
			$image = new Image($id_image);

			if (!$new_path = $image->getPathForCreation())
				$this->_errors[] = Tools::displayError('An error occurred during new folder creation');
			if (!$tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS') || !move_uploaded_file($_FILES['image_product']['tmp_name'], $tmpName))
				$this->_errors[] = Tools::displayError('An error occurred during the image upload');
			else if (!imageResize($tmpName, $new_path.'.'.$image->image_format))
				$this->_errors[] = Tools::displayError('An error occurred while copying image.');
			else if ($method == 'auto')
			{
				$imagesTypes = ImageType::getImagesTypes('products');
				foreach ($imagesTypes as $k => $imageType)
					if (!imageResize($tmpName, $new_path.'-'.stripslashes($imageType['name']).'.'.$image->image_format, $imageType['width'], $imageType['height'], $image->image_format))
						$this->_errors[] = Tools::displayError('An error occurred while copying image:').' '.stripslashes($imageType['name']);
			}

			@unlink($tmpName);
			Module::hookExec('watermark', array('id_image' => $id_image, 'id_product' => $id_product));
		}
	}

	/**
	 * Add or update a product
	 */
	public function submitAddProduct($token = null)
	{
		$className = 'Product';
		$rules = call_user_func(array($this->className, 'getValidationRules'), $this->className);
		$defaultLanguage = $this->context->language;
		$languages = Language::getLanguages(false);

		/* Check required fields */
		foreach ($rules['required'] as $field)
			if (($value = Tools::getValue($field)) == false && $value != '0')
			{
				if (Tools::getValue('id_'.$this->table) && $field == 'passwd')
					continue;
				$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $field, $className).'</b> '.$this->l('is required');
			}

		/* Check multilingual required fields */
		foreach ($rules['requiredLang'] as $fieldLang)
			if (!Tools::getValue($fieldLang.'_'.$defaultLanguage->id))
				$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $fieldLang, $className).'</b> '.$this->l('is required at least in').' '.$defaultLanguage->name;

		/* Check fields sizes */
		foreach ($rules['size'] as $field => $maxLength)
			if ($value = Tools::getValue($field) && Tools::strlen($value) > $maxLength)
				$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $field, $className).'</b> '.$this->l('is too long').' ('.$maxLength.' '.$this->l('chars max').')';

		if (isset($_POST['description_short']))
		{
			$saveShort = $_POST['description_short'];
			$_POST['description_short'] = strip_tags($_POST['description_short']);
		}

		/* Check description short size without html */
		$limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
		if ($limit <= 0) $limit = 400;
		foreach ($languages as $language)
			if ($value = Tools::getValue('description_short_'.$language['id_lang']))
				if (Tools::strlen(strip_tags($value)) > $limit)
					$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), 'description_short').' ('.$language['name'].')</b> '.$this->l('is too long').' : '.$limit.' '.$this->l('chars max').' ('.$this->l('count now').' '.Tools::strlen(strip_tags($value)).')';
		/* Check multilingual fields sizes */
		foreach ($rules['sizeLang'] as $fieldLang => $maxLength)
			foreach ($languages as $language)
				if ($value = Tools::getValue($fieldLang.'_'.$language['id_lang']) && Tools::strlen($value) > $maxLength)
					$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $fieldLang, $className).' ('.$language['name'].')</b> '.$this->l('is too long').' ('.$maxLength.' '.$this->l('chars max').')';
		if (isset($_POST['description_short']))
			$_POST['description_short'] = $saveShort;

		/* Check fields validity */
		foreach ($rules['validate'] as $field => $function)
			if ($value = Tools::getValue($field))
				if (!Validate::$function($value))
					$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $field, $className).'</b> '.$this->l('is invalid');

		/* Check multilingual fields validity */
		foreach ($rules['validateLang'] as $fieldLang => $function)
			foreach ($languages as $language)
				if ($value = Tools::getValue($fieldLang.'_'.$language['id_lang']))
					if (!Validate::$function($value))
						$this->_errors[] = $this->l('the field').' <b>'.call_user_func(array($className, 'displayFieldName'), $fieldLang, $className).' ('.$language['name'].')</b> '.$this->l('is invalid');

		/* Categories */
		$productCats = '';
		if (!Tools::isSubmit('categoryBox') || !sizeof(Tools::getValue('categoryBox')))
			$this->_errors[] = $this->l('product must be in at least one Category');

		if (!is_array(Tools::getValue('categoryBox')) || !in_array(Tools::getValue('id_category_default'), Tools::getValue('categoryBox')))
			$this->_errors[] = $this->l('product must be in the default category');

		/* Tags */
		foreach ($languages as $language)
			if ($value = Tools::getValue('tags_'.$language['id_lang']))
				if (!Validate::isTagsList($value))
					$this->_errors[] = $this->l('Tags list').' ('.$language['name'].') '.$this->l('is invalid');

		if (!sizeof($this->_errors))
		{
			$id = (int)Tools::getValue('id_'.$this->table);
			$tagError = true;

			/* Update an existing product */
			if (isset($id) && !empty($id))
			{
				$object = new $this->className($id);
				if (Validate::isLoadedObject($object))
				{
					$this->_removeTaxFromEcotax();
					$this->copyFromPost($object, $this->table);
					if ($object->update())
					{
						if ($id_reason = (int)Tools::getValue('id_mvt_reason') && Tools::getValue('mvt_quantity') > 0 && $id_reason > 0)
						{
							if (!$object->addStockMvt(Tools::getValue('mvt_quantity'), $id_reason, null, null, $this->context->employee->id))
								$this->_errors[] = Tools::displayError('An error occurred while updating qty.');
						}
						$this->updateAccessories($object);
						$this->updateDownloadProduct($object, 1);
						$this->updateAssoShop((int)$object->id);

						if (!$this->updatePackItems($object))
							$this->_errors[] = Tools::displayError('An error occurred while adding products to the pack.');
						else if (!$object->updateCategories($_POST['categoryBox'], true))
							$this->_errors[] = Tools::displayError('An error occurred while linking object.').' <b>'.$this->table.'</b> '.Tools::displayError('To categories');
						else if (!$this->updateTags($languages, $object))
							$this->_errors[] = Tools::displayError('An error occurred while adding tags.');
						else if ($id_image = $this->addProductImage($object, Tools::getValue('resizer')))
						{
							self::$currentIndex .= '&image_updated='.(int)Tools::getValue('id_image');
							Hook::updateProduct($object);
							Search::indexation(false, $object->id);
							if (Tools::getValue('resizer') == 'man' && isset($id_image) && is_int($id_image) && $id_image)
								Tools::redirectAdmin(self::$currentIndex.'&id_product='.$object->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&edit='.strval(Tools::getValue('productCreated')).'&id_image='.$id_image.'&imageresize&toconf=4&submitAddAndStay='.((Tools::isSubmit('submitAdd'.$this->table.'AndStay') || Tools::getValue('productCreated') == 'on') ? 'on' : 'off').'&token='.(($token ? $token : $this->token)));

							// Save and preview
							if (Tools::isSubmit('submitAddProductAndPreview'))
							{
								$preview_url = $this->context->link->getProductLink($this->getFieldValue($object, 'id'), $this->getFieldValue($object, 'link_rewrite', $this->context->language->id), Category::getLinkRewrite($this->getFieldValue($object, 'id_category_default'), $this->context->language->id), null, null, Context::getContext()->shop->getID());
								if (!$object->active)
								{
									$admin_dir = dirname($_SERVER['PHP_SELF']);
									$admin_dir = substr($admin_dir, strrpos($admin_dir,'/') + 1);
									$token = Tools::encrypt('PreviewProduct'.$object->id);
									if (strpos($preview_url, '?') === false)
										$preview_url .= '?';
									else
										$preview_url .= '&';
									$preview_url .= 'adtoken='.$token.'&ad='.$admin_dir;
								}
								Tools::redirectAdmin($preview_url);
							} else if (Tools::isSubmit('submitAdd'.$this->table.'AndStay') || ($id_image && $id_image !== true)) // Save and stay on same form
							// Save and stay on same form
							if (Tools::isSubmit('submitAdd'.$this->table.'AndStay'))
								Tools::redirectAdmin(self::$currentIndex.'&id_product='.$object->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&addproduct&conf=4&tabs='.(int)(Tools::getValue('tabs')).'&token='.($token ? $token : $this->token));

							// Default behavior (save and back)
							Tools::redirectAdmin(self::$currentIndex.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&conf=4&token='.($token ? $token : $this->token).'&onredirigeici');
						}
					}
					else
						$this->_errors[] = Tools::displayError('An error occurred while updating object.').' <b>'.$this->table.'</b> ('.Db::getInstance()->getMsgError().')';
				}
				else
					$this->_errors[] = Tools::displayError('An error occurred while updating object.').' <b>'.$this->table.'</b> ('.Tools::displayError('Cannot load object').')';
			}

			/* Add a new product */
			else
			{
				$object = new $this->className();
				$this->_removeTaxFromEcotax();
				$this->copyFromPost($object, $this->table);
				if ($object->add())
				{
					$this->updateAssoShop((int)$object->id);
					$this->updateAccessories($object);
					if (!$this->updatePackItems($object))
						$this->_errors[] = Tools::displayError('An error occurred while adding products to the pack.');
					$this->updateDownloadProduct($object);
					if (!sizeof($this->_errors))
					{
						if (!$object->updateCategories($_POST['categoryBox']))
							$this->_errors[] = Tools::displayError('An error occurred while linking object.').' <b>'.$this->table.'</b> '.Tools::displayError('To categories');
						else if (!$this->updateTags($languages, $object))
							$this->_errors[] = Tools::displayError('An error occurred while adding tags.');
						else if ($id_image = $this->addProductImage($object))
						{
							Hook::addProduct($object);
							Search::indexation(false, $object->id);

							// Save and preview
							if (Tools::isSubmit('submitAddProductAndPreview'))
							{
								$preview_url = ($this->context->link->getProductLink($this->getFieldValue($object, 'id'), $this->getFieldValue($object, 'link_rewrite', $this->context->language->id), Category::getLinkRewrite($this->getFieldValue($object, 'id_category_default'), $this->context->language->id)));
								if (!$object->active)
								{
									$admin_dir = dirname($_SERVER['PHP_SELF']);
									$admin_dir = substr($admin_dir, strrpos($admin_dir,'/') + 1);
									$token = Tools::encrypt('PreviewProduct'.$object->id);
									$preview_url .= '&adtoken='.$token.'&ad='.$admin_dir;
								}

								Tools::redirectAdmin($preview_url);
							}

							if (Tools::getValue('resizer') == 'man' && isset($id_image) && is_int($id_image) && $id_image)
								Tools::redirectAdmin(self::$currentIndex.'&id_product='.$object->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&id_image='.$id_image.'&imageresize&toconf=3&submitAddAndStay='.(Tools::isSubmit('submitAdd'.$this->table.'AndStay') ? 'on' : 'off').'&token='.(($token ? $token : $this->token)));
							// Save and stay on same form
							if (Tools::isSubmit('submitAdd'.$this->table.'AndStay'))
								Tools::redirectAdmin(self::$currentIndex.'&id_product='.$object->id.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&addproduct&conf=3&tabs='.(int)(Tools::getValue('tabs')).'&token='.($token ? $token : $this->token));
							// Default behavior (save and back)
							Tools::redirectAdmin(self::$currentIndex.'&id_category='.(!empty($_REQUEST['id_category'])?$_REQUEST['id_category']:'1').'&conf=3&token='.($token ? $token : $this->token));
						}
					}
					else
						$object->delete();
				}
				else
					$this->_errors[] = Tools::displayError('An error occurred while creating object.').' <b>'.$this->table.'</b>';
			}
		}

	}

	private function _removeTaxFromEcotax()
	{
	    $ecotaxTaxRate = Tax::getProductEcotaxRate();
		if ($ecotax = Tools::getValue('ecotax'))
			$_POST['ecotax'] = Tools::ps_round(Tools::getValue('ecotax') / (1 + $ecotaxTaxRate / 100), 6);
	}

	private function _applyTaxToEcotax($product)
	{
	    $ecotaxTaxRate = Tax::getProductEcotaxRate();
		if ($product->ecotax)
			$product->ecotax = Tools::ps_round($product->ecotax * (1 + $ecotaxTaxRate / 100), 2);
	}

	/**
	 * Update product download
	 *
	 * @param object $product Product
	 * @return bool
	 */
	public function updateDownloadProduct($product, $edit = 0, $id_product_attribute = null)
	{
		$filename = '';
		$is_virtual_file = 0;
		$id_product_download = 0;
		
		$is_shareable = 0;
		$virtual_product_name = '';
		$virtual_product_nb_days = 0;
		$virtual_product_filename = '';
		$virtual_product_nb_downloable = 0;
		$virtual_product_expiration_date = '';
		
		$is_shareable_attribute = 0;
		$virtual_product_name_attribute = '';
		$virtual_product_nb_days_attribute = 0;
		$virtual_product_filename_attribute = '';
		$virtual_product_nb_downloable_attribute = 0;
		$virtual_product_expiration_date_attribute = '';
		
		$is_virtual_file = (int) Tools::getValue('is_virtual_file');
	
		/* add or update a virtual product */
		if (Tools::getValue('is_virtual_good') == 'true')
		{
			if (!Tools::getValue('virtual_product_id') && !Tools::getValue('virtual_product_name_attribute') && !empty($is_virtual_file))
			{
				if (!Tools::getValue('virtual_product_name'))
				{
					if (!Tools::getValue('virtual_product_name_attribute') && !empty($id_product_attribute))
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('display filename attribute').'</b> '.$this->l('is required');
						return false;
					}
					else if (!empty($id_product_attribute))
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('display filename').'</b> '.$this->l('is required');
						return false;
					}
				}
			}

			if (Tools::getValue('virtual_product_nb_days') === false && Tools::getValue('virtual_product_nb_days_attribute') === false && !empty($is_virtual_file))
			{
				if (!Tools::getValue('virtual_product_name'))
				{
					if (!Tools::getValue('virtual_product_name_attribute'))
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('number of days attribute').'</b> '.$this->l('is required');
						return false;
					}
					else
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('number of days').'</b> '.$this->l('is required');
						return false;
					}			
				}
			}		
			if (Tools::getValue('virtual_product_expiration_date') AND !Validate::isDate(Tools::getValue('virtual_product_expiration_date') && !empty($is_virtual_file))
			&& Tools::getValue('virtual_product_expiration_date_attribute') AND !Validate::isDate(Tools::getValue('virtual_product_expiration_date_attribute')))
			{
				if (!Tools::getValue('virtual_product_expiration_date'))
				{
					if (!Tools::getValue('virtual_product_expiration_date_attribute'))
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('expiration date attribute').'</b> '.$this->l('is required');
						return false;
					}
					else
					{
						$this->_errors[] = $this->l('the field').' <b>'.$this->l('expiration date').'</b> '.$this->l('is not valid');
						return false;
					}			
				}
			}
			
			// The oos behavior MUST be "Deny orders" for virtual products
			if (Tools::getValue('out_of_stock') != 0)
			{
				$this->_errors[] = $this->l('The "when out of stock" behavior selection must be "deny order" for virtual products');
				return false;
			}
			
			// Trick's 
			if ($edit == 1)
			{
				$id_product_download_attibute = ProductDownload::getIdFromIdAttibute($id_product_attribute);
				$id_product_download = ($id_product_download_attibute) ? (int) $id_product_download_attibute : (int) Tools::getValue('virtual_product_id');
			}
			
			$is_shareable = Tools::getValue('virtual_product_is_shareable');
			$virtual_product_name = Tools::getValue('virtual_product_name');
			$virtual_product_filename = Tools::getValue('virtual_product_filename');
			$virtual_product_nb_days = Tools::getValue('virtual_product_nb_days');
			$virtual_product_nb_downloable = Tools::getValue('virtual_product_nb_downloable');
			$virtual_product_expiration_date = Tools::getValue('virtual_product_expiration_date');
			
			$is_shareable_attribute = Tools::getValue('virtual_product_is_shareable_attribute');
			$virtual_product_name_attribute = Tools::getValue('virtual_product_name_attribute');
			$virtual_product_filename_attribute = Tools::getValue('virtual_product_filename_attribute');
			$virtual_product_nb_days_attribute = Tools::getValue('virtual_product_nb_days_attribute');
			$virtual_product_nb_downloable_attribute = Tools::getValue('virtual_product_nb_downloable_attribute');
			$virtual_product_expiration_date_attribute = Tools::getValue('virtual_product_expiration_date_attribute');
			
			if (!empty($is_shareable_attribute))
				$is_shareable = $is_shareable_attribute;
			
			if (!empty($virtual_product_name_attribute))
				$virtual_product_name = $virtual_product_name_attribute;
			
			if (!empty($virtual_product_nb_days_attribute))
				$virtual_product_nb_days = $virtual_product_nb_days_attribute;
		
			if (!empty($virtual_product_nb_downloable_attribute))
				$virtual_product_nb_downloable = $virtual_product_nb_downloable_attribute;		
		
			if (!empty($virtual_product_expiration_date_attribute))
				$virtual_product_expiration_date = $virtual_product_expiration_date_attribute;

			if (!empty($virtual_product_filename_attribute))
				$filename = $virtual_product_filename_attribute;
			else if ($virtual_product_filename)
				$filename = $virtual_product_filename;
			else
				$filename = ProductDownload::getNewFilename();
			
			$download = new ProductDownload($id_product_download);
			$download->id_product = (int) $product->id;
			$download->id_product_attribute = (int) $id_product_attribute;
			$download->display_filename = $virtual_product_name;
			$download->filename = $filename;
			$download->date_add = date('Y-m-d H:i:s');
			$download->date_expiration = $virtual_product_expiration_date ? $virtual_product_expiration_date.' 23:59:59' : '';
			$download->nb_days_accessible = (int) $virtual_product_nb_days;
			$download->nb_downloadable = (int) $virtual_product_nb_downloable;
			$download->active = 1;
			$download->is_shareable = (int) $is_shareable;
	
			if ($download->save())
				return true;
		}
		else
		{
			/* unactive download product if checkbox not checked */
			if ($edit == 1)
			{
				$id_product_download_attibute = ProductDownload::getIdFromIdAttibute($id_product_attribute);
				$id_product_download = ($id_product_download_attibute) ? (int) $id_product_download_attibute : (int) Tools::getValue('virtual_product_id');
			}
			else
				$id_product_download = ProductDownload::getIdFromIdProduct($product->id);
			
			if (!empty($id_product_download))
			{
				$productDownload = new ProductDownload($id_product_download);
				$productDownload->date_expiration = date('Y-m-d H:i:s', time()-1);
				$productDownload->active = 0;
				return $productDownload->save();
			}
		}
		return false;
	}
	
	public function deleteDownloadProduct($id_product_attribute = NULL)
	{
		if (!empty($id_product_attribute))
		{
			$productDownload = new ProductDownload($id_product_attribute);
			$productDownload->date_expiration = date('Y-m-d H:i:s', time()-1);
			$productDownload->active = 0;
			return $productDownload->save();
		}
		return false;
	}
	
	/**
	 * Update product accessories
	 *
	 * @param object $product Product
	 */
	public function updateAccessories($product)
	{
		$product->deleteAccessories();
		if ($accessories = Tools::getValue('inputAccessories'))
		{
			$accessories_id = array_unique(explode('-', $accessories));
			if (sizeof($accessories_id))
			{
				array_pop($accessories_id);
				$product->changeAccessories($accessories_id);
			}
		}
	}

	/**
	 * Update product tags
	 *
	 * @param array Languages
	 * @param object $product Product
	 * @return boolean Update result
	 */
	public function updateTags($languages, $product)
	{
		$tagError = true;
		/* Reset all tags for THIS product */
		if (!Db::getInstance()->Execute('
		DELETE FROM `'._DB_PREFIX_.'product_tag`
		WHERE `id_product` = '.(int)($product->id)))
			return false;
		/* Assign tags to this product */
		foreach ($languages as $language)
			if ($value = Tools::getValue('tags_'.$language['id_lang']))
				$tagError &= Tag::addTags($language['id_lang'], (int)($product->id), $value);
		return $tagError;
	}

	public function display($token = null)
	{
		if ($id_category = (int)Tools::getValue('id_category'))
			AdminTab::$currentIndex .= '&id_category='.$id_category;
		$this->getList($this->context->language->id, !$this->context->cookie->__get($this->table.'Orderby') ? 'position' : null, !$this->context->cookie->__get($this->table.'Orderway') ? 'ASC' : null, 0, null, $this->context->shop->getID(true));

		$id_category = Tools::getValue('id_category', 1);
		if (!$id_category)
			$id_category = 1;
		echo '<h3>'.(!$this->_listTotal ? ($this->l('No products found')) : ($this->_listTotal.' '.($this->_listTotal > 1 ? $this->l('products') : $this->l('product')))).' '.
		$this->l('in category').' "'.stripslashes($this->_category->getName()).'"</h3>';
		if ($this->tabAccess['add'] === '1')
			echo '<a href="'.self::$currentIndex.'&id_category='.$id_category.'&add'.$this->table.'&token='.($token != null ? $token : $this->token).'"><img src="../img/admin/add.gif" border="0" /> '.$this->l('Add a new product').'</a>';
		echo '<div style="margin:10px;">';
		$this->displayList($token);
		echo '</div>';
	}

	/**
	 * displayList show ordered list of current category
	 *
	 * @param mixed $token
	 * @return void
	 */
	public function displayList($token = null)
	{
		/* Display list header (filtering, pagination and column names) */
		$this->displayListHeader($token);
		if (!sizeof($this->_list))
			echo '<tr><td class="center" colspan="'.(sizeof($this->fieldsDisplay) + 2).'">'.$this->l('No items found').'</td></tr>';

		/* Show the content of the table */
		$this->displayListContent($token);

		/* Close list table and submit button */
		$this->displayListFooter($token);
	}

	/**
	 * Build a categories tree
	 *
	 * @param array $indexedCategories Array with categories where product is indexed (in order to check checkbox)
	 * @param array $categories Categories to list
	 * @param array $current Current category
	 * @param integer $id_category Current category id
	 */
	public static function recurseCategoryForInclude($id_obj, $indexedCategories, $categories, $current, $id_category = 1, $id_category_default = null, $has_suite = array())
	{
		global $done;
		static $irow;

		if (!isset($done[$current['infos']['id_parent']]))
			$done[$current['infos']['id_parent']] = 0;
		$done[$current['infos']['id_parent']] += 1;

		$todo = sizeof($categories[$current['infos']['id_parent']]);
		$doneC = $done[$current['infos']['id_parent']];

		$level = $current['infos']['level_depth'] + 1;

		echo '
		<tr class="'.($irow++ % 2 ? 'alt_row' : '').'">
			<td>
				<input type="checkbox" name="categoryBox[]" class="categoryBox'.($id_category_default == $id_category ? ' id_category_default' : '').'" id="categoryBox_'.$id_category.'" value="'.$id_category.'"'.((in_array($id_category, $indexedCategories) || ((int)(Tools::getValue('id_category')) == $id_category && !(int)($id_obj))) ? ' checked="checked"' : '').' />
			</td>
			<td>
				'.$id_category.'
			</td>
			<td>';
			for ($i = 2; $i < $level; $i++)
				echo '<img src="../img/admin/lvl_'.$has_suite[$i - 2].'.gif" alt="" />';
			echo '<img src="../img/admin/'.($level == 1 ? 'lv1.gif' : 'lv2_'.($todo == $doneC ? 'f' : 'b').'.gif').'" alt="" /> &nbsp;
			<label for="categoryBox_'.$id_category.'" class="t">'.stripslashes($current['infos']['name']).'</label></td>
		</tr>';

		if ($level > 1)
			$has_suite[] = ($todo == $doneC ? 0 : 1);
		if (isset($categories[$id_category]))
			foreach ($categories[$id_category] as $key => $row)
				if ($key != 'infos')
					self::recurseCategoryForInclude($id_obj, $indexedCategories, $categories, $categories[$id_category][$key], $key, $id_category_default, $has_suite);
	}

	public function displayErrors()
	{
		if ($this->includeSubTab('displayErrors'))
			;
		else if ($nbErrors = sizeof($this->_errors))
		{
			echo '<div class="error">
				<img src="../img/admin/error2.png" />
				'.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'
				<ol>';
			foreach ($this->_errors as $error)
				echo '<li>'.$error.'</li>';
			echo '
				</ol>
			</div>';
		}
	}

	private function _displayDraftWarning($active)
	{
		return '<div class="warn draft" style="'.($active ? 'display:none' : '').'">
				<p>
				<span style="float: left">
				<img src="../img/admin/warn2.png" />
				'.$this->l('Your product will be saved as draft').'
				</span>
				<span style="float:right"><a href="#" class="button" style="display: block" onclick="submitAddProductAndPreview()" >'.$this->l('Save and preview').'</a></span>
				<input type="hidden" name="fakeSubmitAddProductAndPreview" id="fakeSubmitAddProductAndPreview" />
				<br class="clear" />
				</p>
	 		</div>';
	}

	public function displayForm($isMainTab = true)
	{
		parent::displayForm();

		if ($id_category_back = (int)(Tools::getValue('id_category')))
			self::$currentIndex .= '&id_category='.$id_category_back;

		if (!($obj = $this->loadObject(true)))
			return;

		$currency = $this->context->currency;

		if ($obj->id)
			self::$currentIndex .= '&id_product='.$obj->id;

		echo '
		<h3>'.$this->l('Current product:').' <span id="current_product" style="font-weight: normal;">'.$this->l('no name').'</span></h3>
		<script type="text/javascript">
			var pos_select = '.(($tab = Tools::getValue('tabs')) ? $tab : '0').';
			'.$this->initCombinationImagesJS().'
			$(document).ready(function(){
				$(\'#id_mvt_reason\').change(function(){
					updateMvtStatus($(this).val());
				});
				updateMvtStatus($(this).val());
			});
			function updateMvtStatus(id_mvt_reason)
			{
				if (id_mvt_reason == -1)
					return $(\'#mvt_sign\').hide();
				if ($(\'#id_mvt_reason option:selected\').attr(\'rel\') == -1)
					$(\'#mvt_sign\').html(\'<img src="../img/admin/arrow_down.png" /> '.$this->l('Decrease your stock').'\');
				else
					$(\'#mvt_sign\').html(\'<img src="../img/admin/arrow_up.png" /> '.$this->l('Increase your stock').'\');
				$(\'#mvt_sign\').show();
			}
		</script>
		<script src="'._PS_JS_DIR_.'tabpane.js" type="text/javascript"></script>
		<link type="text/css" rel="stylesheet" href="../css/tabpane.css" />
		<form action="'.self::$currentIndex.'&token='.Tools::getValue('token').'" method="post" enctype="multipart/form-data" name="product" id="product">
			'.$this->_displayDraftWarning($obj->active).'

			<input type="hidden" name="tabs" id="tabs" value="0" />
			<input type="hidden" name="id_category" value="'.(($id_category = Tools::getValue('id_category')) ? (int)($id_category) : '0').'">
			<div class="tab-pane" id="tabPane1">';
				/* Tabs */
		$this->displayFormInformations($obj, $currency);
		$this->displayFormImages($obj, $this->token);
		if (Combination::isFeatureActive())
			$countAttributes = (int)Db::getInstance()->getValue('SELECT COUNT(id_product) FROM '._DB_PREFIX_.'product_attribute WHERE id_product = '.(int)$obj->id);
		$countAttachments = (int)Db::getInstance()->getValue('SELECT COUNT(id_product) FROM '._DB_PREFIX_.'product_attachment WHERE id_product = '.(int)$obj->id);
		if ($obj->id)
			echo '
			<div class="tab-page" id="step3"><h4 class="tab">3. '.$this->l('Prices').'</h4></div>
			<div class="tab-page" id="step4"><h4 class="tab">4. '.$this->l('Combinations').(isset($countAttributes) ? ' ('.$countAttributes.')' : '').'</h4></div>
			<div class="tab-page" id="step5"><h4 class="tab">5. '.$this->l('Features').'</h4></div>
			<div class="tab-page" id="step6"><h4 class="tab">6. '.$this->l('Customization').'</h4></div>
			<div class="tab-page" id="step7"><h4 class="tab">7. '.$this->l('Attachments').' ('.$countAttachments.')</h4></div>';
		echo '<script type="text/javascript">
					var toload = new Array();
					toload[3] = true;
					toload[4] = true;
					toload[5] = true;
					toload[6] = true;
					toload[7] = true;
					function loadTab(id) {';
		if ($obj->id)
		{
			echo ' 	if (toload[id]) {
							toload[id] = false;

							$.ajax({
								url: "'.dirname(self::$currentIndex).'/ajax.php",
								data: {
									ajaxProductTab: id, id_product: '.$obj->id.',
									token: \''.Tools::getValue('token').'\',
									id_category: '.(int)(Tools::getValue('id_category')).'
								},
								cache: false,
								type: \'POST\',
								success: function(rep) {
									$("#step" + id).html(rep);var languages = new Array();
									if (id == 3)
										populate_attrs();
									if (id == 7)
									{
										$(\'#addAttachment\').click(function() {
											return !$(\'#selectAttachment1 option:selected\').remove().appendTo(\'#selectAttachment2\');
										});
										$(\'#removeAttachment\').click(function() {
											return !$(\'#selectAttachment2 option:selected\').remove().appendTo(\'#selectAttachment1\');
										});
										$(\'#product\').submit(function() {
											$(\'#selectAttachment1 option\').each(function(i) {
												$(this).attr("selected", "selected");
											});
										});
									}
								}
							})
						}';
		}
		echo '	}
				</script>
			</div>
			<div class="clear"></div>
			<input type="hidden" name="id_product_attribute" id="id_product_attribute" value="0" />
			<br />'.$this->_displayDraftWarning($obj->active).'
		</form>';

		if (Tools::getValue('id_category') > 1)
		{
			$productIndex = preg_replace('/(&id_product=[0-9]*)/', '', self::$currentIndex);
			echo '<br /><br />
			<a href="'.$productIndex.($this->token ? '&token='.Tools::getAdminToken('AdminCatalog'.(int)(Tab::getIdFromClassName('AdminCatalog')).(int)$this->context->employee->id) : '').'">
				<img src="../img/admin/arrow2.gif" /> '.$this->l('Back to the category').'
			</a><br />';
		}
	}

	function displayFormPrices($obj, $languages, $defaultLanguage)
	{
		if ($obj->id)
		{
			$shops = Shop::getShops();
			$currencies = Currency::getCurrencies();
			$countries = Country::getCountries($this->context->language->id);
			$groups = Group::getGroups($this->context->language->id);
			$this->_displaySpecificPriceAdditionForm( $this->context->currency, $shops, $currencies, $countries, $groups);
			$this->_displaySpecificPriceModificationForm( $this->context->currency, $shops, $currencies, $countries, $groups);
		}
		else
			echo '<b>'.$this->l('You must save this product before adding specific prices').'.</b>';
	}

	private function _getFinalPrice($specificPrice, $productPrice, $taxRate)
	{
		$price = Tools::ps_round((float)($specificPrice['price']) ? $specificPrice['price'] : $productPrice, 2);
		if (!(float)($specificPrice['reduction']))
			return (float)($specificPrice['price']);
		return ($specificPrice['reduction_type'] == 'amount') ? ($price - $specificPrice['reduction'] / (1 + $taxRate / 100)) : ($price - $price * $specificPrice['reduction']);
	}

	protected function _displaySpecificPriceModificationForm($defaultCurrency, $shops, $currencies, $countries, $groups)
	{
		if (!($obj = $this->loadObject()))
			return;
		$specificPrices = SpecificPrice::getByProductId((int)($obj->id));
		$specificPricePriorities = SpecificPrice::getPriority((int)($obj->id));

		$taxRate = $obj->getTaxesRate(Address::initialize());

		$tmp = array();
		foreach ($shops as $shop)
			$tmp[$shop['id_shop']] = $shop;
		$shops = $tmp;
		$tmp = array();
		foreach ($currencies as $currency)
			$tmp[$currency['id_currency']] = $currency;
		$currencies = $tmp;

		$tmp = array();
		foreach ($countries as $country)
			$tmp[$country['id_country']] = $country;
		$countries = $tmp;

		$tmp = array();
		foreach ($groups as $group)
			$tmp[$group['id_group']] = $group;
		$groups = $tmp;

		echo '
		<h4>'.$this->l('Current specific prices').'</h4>

		<table style="text-align: center;width:100%" class="table" cellpadding="0" cellspacing="0">
			<thead>
				<tr>
					<th class="cell border" style="width: 12%;">'.$this->l('Shop').'</th>
					<th class="cell border" style="width: 12%;">'.$this->l('Currency').'</th>
					<th class="cell border" style="width: 11%;">'.$this->l('Country').'</th>
					<th class="cell border" style="width: 13%;">'.$this->l('Group').'</th>
					<th class="cell border" style="width: 12%;">'.$this->l('Price').' '.($this->context->country->display_tax_label ? $this->l('(tax excl.)') : '').'</th>
					<th class="cell border" style="width: 10%;">'.$this->l('Reduction').'</th>
					<th class="cell border" style="width: 15%;">'.$this->l('Period').'</th>
					<th class="cell border" style="width: 10%;">'.$this->l('From (quantity)').'</th>
					<th class="cell border" style="width: 15%;">'.$this->l('Final price').' '.($this->context->country->display_tax_label ? $this->l('(tax excl.)') : '').'</th>
					<th class="cell border" style="width: 2%;">'.$this->l('Action').'</th>
				</tr>
			</thead>
			<tbody>';
		if (!is_array($specificPrices) || !sizeof($specificPrices))
			echo '
				<tr>
					<td colspan="9">'.$this->l('No specific prices').'</td>
				</tr>';
		else
		{
			$i = 0;
			foreach ($specificPrices as $specificPrice)
			{
				$current_specific_currency = $currencies[($specificPrice['id_currency'] ? $specificPrice['id_currency'] : $defaultCurrency->id)];
				if ($specificPrice['reduction_type'] == 'percentage')
					$reduction = ($specificPrice['reduction'] * 100).' %';
				else
					$reduction = Tools::displayPrice(Tools::ps_round($specificPrice['reduction'], 2), $current_specific_currency);

				if ($specificPrice['from'] == '0000-00-00 00:00:00' && $specificPrice['to'] == '0000-00-00 00:00:00')
					$period = $this->l('Unlimited');
				else
					$period = $this->l('From').' '.($specificPrice['from'] != '0000-00-00 00:00:00' ? $specificPrice['from'] : '0000-00-00 00:00:00').'<br />'.$this->l('To').' '.($specificPrice['to'] != '0000-00-00 00:00:00' ? $specificPrice['to'] : '0000-00-00 00:00:00');
				echo '
				<tr '.($i%2 ? 'class="alt_row"' : '').'>
					<td class="cell border">'.($specificPrice['id_shop'] ? $shops[$specificPrice['id_shop']]['name'] : $this->l('All shops')).'</td>
					<td class="cell border">'.($specificPrice['id_currency'] ? $currencies[$specificPrice['id_currency']]['name'] : $this->l('All currencies')).'</td>
					<td class="cell border">'.($specificPrice['id_country'] ? $countries[$specificPrice['id_country']]['name'] : $this->l('All countries')).'</td>
					<td class="cell border">'.($specificPrice['id_group'] ? $groups[$specificPrice['id_group']]['name'] : $this->l('All groups')).'</td>
					<td class="cell border">'.Tools::displayPrice((float)$specificPrice['price'], $current_specific_currency).'</td>
					<td class="cell border">'.$reduction.'</td>
					<td class="cell border">'.$period.'</td>
					<td class="cell border">'.$specificPrice['from_quantity'].'</th>
					<td class="cell border"><b>'.Tools::displayPrice(Tools::ps_round((float)($this->_getFinalPrice($specificPrice, (float)($obj->price), $taxRate)), 2), $current_specific_currency).'</b></td>
					<td class="cell border"><a href="'.self::$currentIndex.(Tools::getValue('id_category') ? '&id_category='.Tools::getValue('id_category') : '').'&id_product='.(int)(Tools::getValue('id_product')).'&updateproduct&deleteSpecificPrice&id_specific_price='.(int)($specificPrice['id_specific_price']).'&token='.Tools::getValue('token').'"><img src="../img/admin/delete.gif" alt="'.$this->l('Delete').'" /></a></td>
				</tr>';
				$i++;
			}
		}
		echo '
			</tbody>
		</table>';

		echo '
		<script type="text/javascript">
			var currencies = new Array();
			currencies[0] = new Array();
			currencies[0]["sign"] = "'.$defaultCurrency->sign.'";
			currencies[0]["format"] = '.$defaultCurrency->format.';
			';
			foreach ($currencies as $currency)
			{
				echo '
				currencies['.$currency['id_currency'].'] = new Array();
				currencies['.$currency['id_currency'].']["sign"] = "'.$currency['sign'].'";
				currencies['.$currency['id_currency'].']["format"] = '.$currency['format'].';
				';
			}
			echo '
		</script>
		';

		echo '
		<hr />
		<h4>'.$this->l('Priorities management').'</h4>
		<div class="hint clear" style="display:block;">
				'.$this->l('Sometimes one customer could fit in multiple rules, priorities allows you to define which rule to apply.').'
		</div>
	<br />
		<label>'.$this->l('Priorities:').'</label>
		<div class="margin-form">
			<select name="specificPricePriority[]">
				<option value="id_shop"'.($specificPricePriorities[0] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->l('Shop').'</option>
				<option value="id_currency"'.($specificPricePriorities[0] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
				<option value="id_country"'.($specificPricePriorities[0] == 'id_country' ? ' selected="selected"' : '').'>'.$this->l('Country').'</option>
				<option value="id_group"'.($specificPricePriorities[0] == 'id_group' ? ' selected="selected"' : '').'>'.$this->l('Group').'</option>
			</select>
			<select name="specificPricePriority[]">
				<option value="id_shop"'.($specificPricePriorities[1] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->l('Shop').'</option>
				<option value="id_currency"'.($specificPricePriorities[1] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
				<option value="id_country"'.($specificPricePriorities[1] == 'id_country' ? ' selected="selected"' : '').'>'.$this->l('Country').'</option>
				<option value="id_group"'.($specificPricePriorities[1] == 'id_group' ? ' selected="selected"' : '').'>'.$this->l('Group').'</option>
			</select>
			&gt;
			<select name="specificPricePriority[]">
				<option value="id_shop"'.($specificPricePriorities[2] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->l('Shop').'</option>
				<option value="id_currency"'.($specificPricePriorities[2] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
				<option value="id_country"'.($specificPricePriorities[2] == 'id_country' ? ' selected="selected"' : '').'>'.$this->l('Country').'</option>
				<option value="id_group"'.($specificPricePriorities[2] == 'id_group' ? ' selected="selected"' : '').'>'.$this->l('Group').'</option>
			</select>
			&gt;
			<select name="specificPricePriority[]">
				<option value="id_shop"'.($specificPricePriorities[3] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->l('Shop').'</option>
				<option value="id_currency"'.($specificPricePriorities[3] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
				<option value="id_country"'.($specificPricePriorities[3] == 'id_country' ? ' selected="selected"' : '').'>'.$this->l('Country').'</option>
				<option value="id_group"'.($specificPricePriorities[3] == 'id_group' ? ' selected="selected"' : '').'>'.$this->l('Group').'</option>
			</select>
		</div>

		<div class="margin-form">
			<input type="checkbox" name="specificPricePriorityToAll" id="specificPricePriorityToAll" /> <label class="t" for="specificPricePriorityToAll">'.$this->l('Apply to all products').'</label>
		</div>

		<div class="margin-form">
			<input class="button" type="submit" name="submitSpecificPricePriorities" value="'.$this->l('Apply').'" />
		</div>
		';
	}

	protected function _displaySpecificPriceAdditionForm($defaultCurrency, $shops, $currencies, $countries, $groups)
	{
		if (!($product = $this->loadObject()))
			return;

		echo '
		<a href="#" onclick="$(\'#add_specific_price\').slideToggle();return false;"><img src="../img/admin/add.gif" alt="" /> '.$this->l('Add a new specific price').'</a>

		<div id="add_specific_price" style="display: none;">
			<input type="hidden" name="sp_id_shop" value="0" />
			<label>'.$this->l('For:').'</label>
			<div class="margin-form">
				<select name="sp_id_shop">
					<option value="0">'.$this->l('All shops').'</option>';
				foreach ($shops as $shop)
					echo '<option value="'.(int)($shop['id_shop']).'">'.Tools::htmlentitiesUTF8($shop['name']).'</option>';
				echo '
				</select>
	            &gt;
				<select name="sp_id_currency" id="spm_currency_0" onchange="changeCurrencySpecificPrice(0);">
					<option value="0">'.$this->l('All currencies').'</option>';
				foreach ($currencies as $currency)
					echo '<option value="'.(int)($currency['id_currency']).'">'.Tools::htmlentitiesUTF8($currency['name']).'</option>';
				echo '
				</select>
	            &gt;
				<select name="sp_id_country">
					<option value="0">'.$this->l('All countries').'</option>';
				foreach ($countries as $country)
					echo '<option value="'.(int)($country['id_country']).'">'.Tools::htmlentitiesUTF8($country['name']).'</option>';
				echo '
				</select>
	            &gt;
				<select name="sp_id_group">
					<option value="0">'.$this->l('All groups').'</option>';
				foreach ($groups as $group)
					echo '	<option value="'.(int)($group['id_group']).'">'.Tools::htmlentitiesUTF8($group['name']).'</option>';
				echo '
				</select>
			</div>

			<label>'.$this->l('Available from:').'</label>
			<div class="margin-form">
				<input type="text" name="sp_from" value="" style="text-align: center" id="sp_from" /><span style="font-weight:bold; color:#000000; font-size:12px"> '.$this->l('to').'</span>
				<input type="text" name="sp_to" value="" style="text-align: center" id="sp_to" />
			</div>

			<label>'.$this->l('Starting at').'</label>
			<div class="margin-form">
				<input type="text" name="sp_from_quantity" value="1" size="3" /> <span style="font-weight:bold; color:#000000; font-size:12px">'.$this->l('unit').'</span>
			</div>

			<label>'.$this->l('Product price');
				if ($this->context->country->display_tax_label)
					echo ' '.$this->l('(tax excl.):');
			echo '</label>
			<div class="margin-form">
				<span id="spm_currency_sign_pre_0" style="font-weight:bold; color:#000000; font-size:12px">'.($defaultCurrency->format == 1 ? ' '.$defaultCurrency->sign : '').'</span>
				<input type="text" name="sp_price" value="0" size="11" />
				<span id="spm_currency_sign_post_0" style="font-weight:bold; color:#000000; font-size:12px">'.($defaultCurrency->format == 2 ? ' '.$defaultCurrency->sign : '').'</span>
				<span id="sp_current_ht_price" > ('.$this->l('Current:').' '.Tools::displayPrice((float)($product->price), $defaultCurrency).')</span>
				<div class="hint clear" style="display:block;">
					'.$this->l('You can set this value at 0 in order to apply the default price').'
				</div>
			</div>

			<label>'.$this->l('Apply a discount of:').'</label>
			<div class="margin-form">
	    		<input type="text" name="sp_reduction" value="0.00" size="11" />
				<select name="sp_reduction_type">
					<option selected="selected">---</option>
					<option value="amount">'.$this->l('Amount').'</option>
					<option value="percentage">'.$this->l('Percentage').'</option>
				</select>
				'.$this->l('(if set to "amount", the tax is included)').'
			</div>

			<div class="margin-form">
				<input type="submit" name="submitPriceAddition" value="'.$this->l('Add').'" class="button" />
			</div>
		</div>
		<hr />
		';
		includeDatepicker(array('sp_from', 'sp_to'), true);
	}

	private function _getCustomizationFieldIds($labels, $alreadyGenerated, $obj)
	{
		$customizableFieldIds = array();
		if (isset($labels[Product::CUSTOMIZE_FILE]))
			foreach ($labels[Product::CUSTOMIZE_FILE] as $id_customization_field => $label)
				$customizableFieldIds[] = 'label_'.Product::CUSTOMIZE_FILE.'_'.(int)($id_customization_field);
		if (isset($labels[Product::CUSTOMIZE_TEXTFIELD]))
			foreach ($labels[Product::CUSTOMIZE_TEXTFIELD] as $id_customization_field => $label)
				$customizableFieldIds[] = 'label_'.Product::CUSTOMIZE_TEXTFIELD.'_'.(int)($id_customization_field);
		$j = 0;
		for ($i = $alreadyGenerated[Product::CUSTOMIZE_FILE]; $i < (int)($this->getFieldValue($obj, 'uploadable_files')); $i++)
			$customizableFieldIds[] = 'newLabel_'.Product::CUSTOMIZE_FILE.'_'.$j++;
		$j = 0;
		for ($i = $alreadyGenerated[Product::CUSTOMIZE_TEXTFIELD]; $i < (int)($this->getFieldValue($obj, 'text_fields')); $i++)
			$customizableFieldIds[] = 'newLabel_'.Product::CUSTOMIZE_TEXTFIELD.'_'.$j++;
		return implode('¤', $customizableFieldIds);
	}

	private function _displayLabelField(&$label, $languages, $defaultLanguage, $type, $fieldIds, $id_customization_field)
	{
		$fieldsName = 'label_'.$type.'_'.(int)($id_customization_field);
		$fieldsContainerName = 'labelContainer_'.$type.'_'.(int)($id_customization_field);
		echo '<div id="'.$fieldsContainerName.'" class="translatable clear" style="line-height: 18px">';
		foreach ($languages as $language)
		{
			$fieldName = 'label_'.$type.'_'.(int)($id_customization_field).'_'.(int)($language['id_lang']);
			$text = (isset($label[(int)($language['id_lang'])])) ? $label[(int)($language['id_lang'])]['name'] : '';
			echo '<div class="lang_'.$language['id_lang'].'" id="'.$fieldName.'" style="display: '.((int)($language['id_lang']) == (int)($defaultLanguage) ? 'block' : 'none').'; clear: left; float: left; padding-bottom: 4px;">
						<div style="margin-right: 6px; float:left; text-align:right;">#'.(int)($id_customization_field).'</div><input type="text" name="'.$fieldName.'" value="'.htmlentities($text, ENT_COMPAT, 'UTF-8').'" style="float: left" />
					</div>';
		}

		$required = (isset($label[(int)($language['id_lang'])])) ? $label[(int)($language['id_lang'])]['required'] : false;
		echo '</div>
				<div style="margin: 3px 0 0 3px; font-size: 11px">
					<input type="checkbox" name="require_'.$type.'_'.(int)($id_customization_field).'" id="require_'.$type.'_'.(int)($id_customization_field).'" value="1" '.($required ? 'checked="checked"' : '').' style="float: left; margin: 0 4px"/><label for="require_'.$type.'_'.(int)($id_customization_field).'" style="float: none; font-weight: normal;"> '.$this->l('required').'</label>
				</div>';
	}

	private function _displayLabelFields(&$obj, &$labels, $languages, $defaultLanguage, $type)
	{
		$type = (int)($type);
		$labelGenerated = array(Product::CUSTOMIZE_FILE => (isset($labels[Product::CUSTOMIZE_FILE]) ? count($labels[Product::CUSTOMIZE_FILE]) : 0), Product::CUSTOMIZE_TEXTFIELD => (isset($labels[Product::CUSTOMIZE_TEXTFIELD]) ? count($labels[Product::CUSTOMIZE_TEXTFIELD]) : 0));

		$fieldIds = $this->_getCustomizationFieldIds($labels, $labelGenerated, $obj);
		if (isset($labels[$type]))
			foreach ($labels[$type] as $id_customization_field => $label)
				$this->_displayLabelField($label, $languages, $defaultLanguage, $type, $fieldIds, (int)($id_customization_field));
	}

	function displayFormCustomization($obj, $languages, $defaultLanguage)
	{
		parent::displayForm();
		$labels = $obj->getCustomizationFields();
		$defaultIso = Language::getIsoById($defaultLanguage);

		$hasFileLabels = (int)($this->getFieldValue($obj, 'uploadable_files'));
		$hasTextLabels = (int)($this->getFieldValue($obj, 'text_fields'));

		echo '
			<table cellpadding="5">
				<tr>
					<td colspan="2"><b>'.$this->l('Add or modify customizable properties').'</b></td>
				</tr>
			</table>
			<hr style="width:100%;" /><br />
			<table cellpadding="5" style="width:100%">
				<tr>
					<td style="width:150px;text-align:right;padding-right:10px;font-weight:bold;vertical-align:top;" valign="top">'.$this->l('File fields:').'</td>
					<td style="padding-bottom:5px;">
						<input type="text" name="uploadable_files" id="uploadable_files" size="4" value="'.((int)($this->getFieldValue($obj, 'uploadable_files')) ? (int)($this->getFieldValue($obj, 'uploadable_files')) : '0').'" />
						<p>'.$this->l('Number of upload file fields displayed').'</p>
					</td>
				</tr>
				<tr>
					<td style="width:150px;text-align:right;padding-right:10px;font-weight:bold;vertical-align:top;" valign="top">'.$this->l('Text fields:').'</td>
					<td style="padding-bottom:5px;">
						<input type="text" name="text_fields" id="text_fields" size="4" value="'.((int)($this->getFieldValue($obj, 'text_fields')) ? (int)($this->getFieldValue($obj, 'text_fields')) : '0').'" />
						<p>'.$this->l('Number of text fields displayed').'</p>
					</td>
				</tr>
				<tr>
					<td colspan="2" style="text-align:center;">
						<input type="submit" name="submitCustomizationConfiguration" value="'.$this->l('Update settings').'" class="button" onclick="this.form.action += \'&addproduct&tabs=5\';" />
					</td>
				</tr>';

				if ($hasFileLabels)
				{
					echo '
				<tr><td colspan="2"><hr style="width:100%;" /></td></tr>
				<tr>
					<td style="width:150px" valign="top">'.$this->l('Files fields:').'</td>
					<td>';
					$this->_displayLabelFields($obj, $labels, $languages, $defaultLanguage, Product::CUSTOMIZE_FILE);
					echo '
					</td>
				</tr>';
				}

				if ($hasTextLabels)
				{
					echo '
				<tr><td colspan="2"><hr style="width:100%;" /></td></tr>
				<tr>
					<td style="width:150px" valign="top">'.$this->l('Text fields:').'</td>
					<td>';
					$this->_displayLabelFields($obj, $labels, $languages, $defaultLanguage, Product::CUSTOMIZE_TEXTFIELD);
					echo '
					</td>
				</tr>';
				}

				echo '
				<tr>
					<td colspan="2" style="text-align:center;">';
				if ($hasFileLabels || $hasTextLabels)
					echo '<input type="submit" name="submitProductCustomization" id="submitProductCustomization" value="'.$this->l('Save labels').'" class="button" onclick="this.form.action += \'&addproduct&tabs=5\';" style="margin-top: 9px" />';
				echo '
					</td>
				</tr>
			</table>';
	}

	function displayFormAttachments($obj, $languages, $defaultLanguage)
	{
		if (!($obj = $this->loadObject(true)))
			return;
		$languages = Language::getLanguages(false);
		$attach1 = Attachment::getAttachments($this->context->language->id, $obj->id, true);
		$attach2 = Attachment::getAttachments($this->context->language->id, $obj->id, false);

				echo '
		'.($obj->id ? '<input type="hidden" name="id_'.$this->table.'" value="'.$obj->id.'" />' : '').'
			<fieldset><legend><img src="../img/t/AdminAttachments.gif" />'.$this->l('Attachment').'</legend>
				<label>'.$this->l('Filename:').' </label>
				<div class="margin-form">';
		foreach ($languages as $language)
			echo '	<div id="attachment_name_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $defaultLanguage ? 'block' : 'none').'; float: left;">
						<input size="33" type="text" name="attachment_name_'.$language['id_lang'].'" value="'.htmlentities($this->getFieldValue($obj, 'attachment_name', (int)($language['id_lang'])), ENT_COMPAT, 'UTF-8').'" /><sup> *</sup>
					</div>';
		$this->displayFlags($languages, $defaultLanguage, 'attachment_name¤attachment_description', 'attachment_name');
		echo '	</div>
				<div class="clear">&nbsp;</div>
				<label>'.$this->l('Description:').' </label>
				<div class="margin-form">';
		foreach ($languages as $language)
			echo '	<div id="attachment_description_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $defaultLanguage ? 'block' : 'none').'; float: left;">
						<textarea name="attachment_description_'.$language['id_lang'].'">'.htmlentities($this->getFieldValue($obj, 'attachment_description', (int)($language['id_lang'])), ENT_COMPAT, 'UTF-8').'</textarea>
					</div>';
		$this->displayFlags($languages, $defaultLanguage, 'attachment_name¤attachment_description', 'attachment_description');
		echo '	</div>
				<div class="clear">&nbsp;</div>
				<label>'.$this->l('File').'</label>
				<div class="margin-form">
					<p><input type="file" name="attachment_file" /></p>
					<p>'.$this->l('Upload file from your computer').'</p>
				</div>
				<div class="clear">&nbsp;</div>
				<div class="margin-form">
					<input type="submit" value="'.$this->l('Add a new attachment file').'" name="submitAddAttachments" class="button" />
				</div>
				<div class="small"><sup>*</sup> '.$this->l('Required field').'</div>
			</fieldset>
		<div class="clear">&nbsp;</div>
		<table>
			<tr>
				<td>
					<p>'.$this->l('Attachments for this product:').'</p>
					<select multiple id="selectAttachment1" name="attachments[]" style="width:300px;height:160px;">';
			foreach ($attach1 as $attach)
				echo '<option value="'.$attach['id_attachment'].'">'.$attach['name'].'</option>';
			echo '	</select><br /><br />
					<a href="#" id="addAttachment" style="text-align:center;display:block;border:1px solid #aaa;text-decoration:none;background-color:#fafafa;color:#123456;margin:2px;padding:2px">
						'.$this->l('Remove').' &gt;&gt;
					</a>
				</td>
				<td style="padding-left:20px;">
					<p>'.$this->l('Available attachments:').'</p>
					<select multiple id="selectAttachment2" style="width:300px;height:160px;">';
			foreach ($attach2 as $attach)
				echo '<option value="'.$attach['id_attachment'].'">'.$attach['name'].'</option>';
			echo '	</select><br /><br />
					<a href="#" id="removeAttachment" style="text-align:center;display:block;border:1px solid #aaa;text-decoration:none;background-color:#fafafa;color:#123456;margin:2px;padding:2px">
						&lt;&lt; '.$this->l('Add').'
					</a>
				</div>
				</td>
			</tr>
		</table>
		<div class="clear">&nbsp;</div>
		<input type="submit" name="submitAttachments" id="submitAttachments" value="'.$this->l('Update attachments').'" class="button" />';
	}

	function displayFormInformations($obj, $currency)
	{
		parent::displayForm(false);

		$has_attribute = $obj->hasAttributes();
		$qty = $obj->getStock();
		$cover = Product::getCover($obj->id);
		$this->_applyTaxToEcotax($obj);

		/*
		* Form for add a virtual product like software, mp3, etc...
		*/
		$productDownload = new ProductDownload();
		if ($id_product_download = $productDownload->getIdFromIdProduct($this->getFieldValue($obj, 'id')))
			$productDownload = new ProductDownload($id_product_download);

		$hidden = $display_filename = $check = '';
	?>
	
    <script type="text/javascript">
    // <![CDATA[
    	ThickboxI18nImage = '<?php echo $this->l('Image') ?>';
    	ThickboxI18nOf = '<?php echo $this->l('of') ?>';
    	ThickboxI18nClose = '<?php echo $this->l('Close') ?>';
    	ThickboxI18nOrEscKey = '<?php echo $this->l('(or "Esc")') ?>';
    	ThickboxI18nNext = '<?php echo $this->l('Next >') ?>';
    	ThickboxI18nPrev = '<?php echo $this->l('< Previous') ?>';
    	tb_pathToImage = '../img/loadingAnimation.gif';
    //]]>
    </script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>jquery/thickbox-modified.js"></script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>jquery/ajaxfileupload.js"></script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>date.js"></script>
	<style type="text/css">
		<!--
		@import url(<?php echo _PS_CSS_DIR_?>thickbox.css);
		-->
	</style>
	<script type="text/javascript">
	//<![CDATA[
	function toggleVirtualProduct(elt)
	{
		$("#is_virtual_file_product").hide();
		$("#virtual_good_attributes").hide();
			
		if (elt.checked)
		{
			$('#virtual_good').show('slow');
			$('#virtual_good_more').show('slow');
			getE('out_of_stock_1').checked = 'checked';
			getE('out_of_stock_2').disabled = 'disabled';
			getE('out_of_stock_3').disabled = 'disabled';
			getE('label_out_of_stock_2').setAttribute('for', '');
			getE('label_out_of_stock_3').setAttribute('for', '');
		}
		else
		{
			$('#virtual_good').hide('slow');
			$('#virtual_good_more').hide('slow');
			getE('out_of_stock_2').disabled = false;
			getE('out_of_stock_3').disabled = false;
			getE('label_out_of_stock_2').setAttribute('for', 'out_of_stock_2');
			getE('label_out_of_stock_3').setAttribute('for', 'out_of_stock_3');
		}
	}

	function uploadFile()
	{
		$.ajaxFileUpload (
			{
				url:'./uploadProductFile.php',
				secureuri:false,
				fileElementId:'virtual_product_file',
				dataType: 'xml',
				success: function (data, status)
				{
					data = data.getElementsByTagName('return')[0];
					var result = data.getAttribute("result");
					var msg = data.getAttribute("msg");
					var fileName = data.getAttribute("filename")
					if(result == "error")
						$("#upload-confirmation").html('<p>error: ' + msg + '</p>');
					else
					{
						$('#virtual_product_file').remove();
						$('#virtual_product_file_label').hide();
						$('#file_missing').hide();
						$('#delete_downloadable_product').show();
						$('#virtual_product_name').attr('value', fileName);
						$('#upload-confirmation').html(
							'<a class="link" href="get-file-admin.php?file='+msg+'&filename='+fileName+'"><?php echo $this->l('The file') ?>&nbsp;"' + fileName + '"&nbsp;<?php echo $this->l('has successfully been uploaded') ?></a>' +
							'<input type="hidden" id="virtual_product_filename" name="virtual_product_filename" value="' + msg + '" />');
					}
				}
			}
		);
	}	

	function uploadFile2()
	{
			var link = '';
			$.ajaxFileUpload (
			{
				url:'./uploadProductFileAttribute.php',
				secureuri:false,
				fileElementId:'virtual_product_file_attribute',
				dataType: 'xml',
				success: function (data, status)
				{
					data = data.getElementsByTagName('return')[0];
					var result = data.getAttribute("result");
					var msg = data.getAttribute("msg");
					var fileName = data.getAttribute("filename");
					if(result == "error")
						$("#upload-confirmation2").html('<p>error: ' + msg + '</p>');
					else
					{
						$('#virtual_product_file_attribute').remove();
						$('#virtual_product_file_label').hide();
						$('#file_missing').hide();
						$('#delete_downloadable_product_attribute').show();
						$('#virtual_product_name_attribute').attr('value', fileName);
						$('#upload-confirmation2').html(
							'<a class="link" href="get-file-admin.php?file='+msg+'&filename='+fileName+'"><?php echo $this->l('The file') ?>&nbsp;"' + fileName + '"&nbsp;<?php echo $this->l('has successfully been uploaded') ?></a>' +
							'<input type="hidden" id="virtual_product_filename_attribute" name="virtual_product_filename_attribute" value="' + msg + '" />');
						
						link = $("#delete_downloadable_product_attribute").attr('href');		
						$("#delete_downloadable_product_attribute").attr('href', link+"&file="+msg);
					}
				}
			}
		);
	}
	//]]>
	</script>
	<?php
	
	if(($productDownload->id OR Tools::getValue('is_virtual_good')=='true') AND $productDownload->active) 
		$check = 'checked="checked"';
		
	if(!$productDownload->id OR !$productDownload->active) 
		$hidden = 'style="display:none;"';
	
	$cache_default_attribute = $this->getFieldValue($obj, 'cache_default_attribute');
			
	 echo '
		<div class="tab-page" id="step1">
			<h4 class="tab">1. '.$this->l('Info.').'</h4>
			<script type="text/javascript">
				$(document).ready(function() {
					updateCurrentText();
					updateFriendlyURL();
					$.ajax({
						url: "'.dirname(self::$currentIndex).'/ajax.php",
						cache: false,
						dataType: "json",
						data: "ajaxProductManufacturers=1",
						success: function(j) {
							var options = $("select#id_manufacturer").html();
							if (j)
							for (var i = 0; i < j.length; i++)
								options += \'<option value="\' + j[i].optionValue + \'">\' + j[i].optionDisplay + \'</option>\';
							$("select#id_manufacturer").html(options);
						},
						error: function(XMLHttpRequest, textStatus, errorThrown)
						{
							alert(\'Manufacturer ajax error: \'+textStatus);
						}

					});
					$.ajax({
						url: "'.dirname(self::$currentIndex).'/ajax.php",
						cache: false,
						dataType: "json",
						data: "ajaxProductSuppliers=1",
						success: function(j) {
							var options = $("select#id_supplier").html();
							if (j)
							for (var i = 0; i < j.length; i++)
								options += \'<option value="\' + j[i].optionValue + \'">\' + j[i].optionDisplay + \'</option>\';
							$("select#id_supplier").html(options);
						},
						error: function(XMLHttpRequest, textStatus, errorThrown)
						{
							alert(\'Supplier ajax error: \'+textStatus);
						}

					});
					if ($(\'#available_for_order\').is(\':checked\')){
						$(\'#show_price\').attr(\'checked\', \'checked\');
						$(\'#show_price\').attr(\'disabled\', \'disabled\');
					}
					else {
						$(\'#show_price\').attr(\'disabled\', \'\');
					}
				});
			</script>
			<b>'.$this->l('Product global information').'</b>&nbsp;-&nbsp;';
		$preview_url = '';
		if (isset($obj->id))
		{
			$preview_url = ($this->context->link->getProductLink($this->getFieldValue($obj, 'id'), $this->getFieldValue($obj, 'link_rewrite', $this->_defaultFormLanguage), Category::getLinkRewrite($this->getFieldValue($obj, 'id_category_default'), $this->context->language->id)));
			if (!$obj->active)
			{
				$admin_dir = dirname($_SERVER['PHP_SELF']);
				$admin_dir = substr($admin_dir, strrpos($admin_dir,'/') + 1);
				$token = Tools::encrypt('PreviewProduct'.$obj->id);

				$preview_url .= $obj->active ? '' : '&adtoken='.$token.'&ad='.$admin_dir;
			}

			echo '
			<a href="index.php?tab=AdminCatalog&id_product='.$obj->id.'&deleteproduct&token='.$this->token.'" style="float:right;"
			onclick="return confirm(\''.$this->l('Are you sure?', __CLASS__, true, false).'\');">
			<img src="../img/admin/delete.gif" alt="'.$this->l('Delete this product').'" title="'.$this->l('Delete this product').'" /> '.$this->l('Delete this product').'</a>
			<a href="'.$preview_url.'" target="_blank"><img src="../img/admin/details.gif" alt="'.$this->l('View product in shop').'" title="'.$this->l('View product in shop').'" /> '.$this->l('View product in shop').'</a>';

			if (file_exists(_PS_MODULE_DIR_.'statsproduct/statsproduct.php'))
				echo '&nbsp;-&nbsp;<a href="index.php?tab=AdminStats&module=statsproduct&id_product='.$obj->id.'&token='.Tools::getAdminToken('AdminStats'.(int)(Tab::getIdFromClassName('AdminStats')).(int)$this->context->employee->id).'"><img src="../modules/statsproduct/logo.gif" alt="'.$this->l('View product sales').'" title="'.$this->l('View product sales').'" /> '.$this->l('View product sales').'</a>';
		}
		echo '
			<hr class="clear"/>
			<br />
				<table cellpadding="5" style="width: 50%; float: left; margin-right: 20px; border-right: 1px solid #E0D0B1;">
					<tr>
						<td class="col-left">'.$this->l('Name:').'</td>
						<td style="padding-bottom:5px;" class="translatable">';
		foreach ($this->_languages as $language)
			echo '		<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
								<input size="43" type="text" id="name_'.$language['id_lang'].'" name="name_'.$language['id_lang'].'"
								value="'.stripslashes(htmlspecialchars($this->getFieldValue($obj, 'name', $language['id_lang']))).'"'.((!$obj->id) ? ' onkeyup="if (isArrowKey(event)) return; copy2friendlyURL();"' : '').' onkeyup="if (isArrowKey(event)) return; updateCurrentText();" onchange="updateCurrentText();" /><sup> *</sup>
								<span class="hint" name="help_box">'.$this->l('Invalid characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
							</div>';
		echo '		</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Reference:').'</td>
						<td style="padding-bottom:5px;">
							<input size="55" type="text" name="reference" value="'.htmlentities($this->getFieldValue($obj, 'reference'), ENT_COMPAT, 'UTF-8').'" style="width: 130px; margin-right: 44px;" />
							<span class="hint" name="help_box">'.$this->l('Special characters allowed:').' .-_#\<span class="hint-pointer">&nbsp;</span></span>
						</td>
					</tr>
                	<tr>
						<td class="col-left">'.$this->l('Supplier Reference:').'</td>
						<td style="padding-bottom:5px;">
							<input size="55" type="text" name="supplier_reference" value="'.htmlentities($this->getFieldValue($obj, 'supplier_reference'), ENT_COMPAT, 'UTF-8').'" style="width: 130px; margin-right: 44px;" />
							<span class="hint" name="help_box">'.$this->l('Special characters allowed:').' .-_#\<span class="hint-pointer">&nbsp;</span></span>
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('EAN13 or JAN:').'</td>
						<td style="padding-bottom:5px;">
							<input size="55" maxlength="13" type="text" name="ean13" value="'.htmlentities($this->getFieldValue($obj, 'ean13'), ENT_COMPAT, 'UTF-8').'" style="width: 130px; margin-right: 5px;" /> <span class="small">'.$this->l('(Europe, Japan)').'</span>
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('UPC:').'</td>
						<td style="padding-bottom:5px;">
							<input size="55" maxlength="12" type="text" name="upc" value="'.htmlentities($this->getFieldValue($obj, 'upc'), ENT_COMPAT, 'UTF-8').'" style="width: 130px; margin-right: 5px;" /> <span class="small">'.$this->l('(US, Canada)').'</span>
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Location (warehouse):').'</td>
						<td style="padding-bottom:5px;">
							<input size="55" type="text" name="location" value="'.htmlentities($this->getFieldValue($obj, 'location'), ENT_COMPAT, 'UTF-8').'" style="width: 130px; margin-right: 44px;" />
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Width ( package ) :').'</td>
						<td style="padding-bottom:5px;">
							<input size="6" maxlength="6" name="width" type="text" value="'.htmlentities($this->getFieldValue($obj, 'width'), ENT_COMPAT, 'UTF-8').'" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" /> '.Configuration::get('PS_DIMENSION_UNIT').'
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Height ( package ) :').'</td>
						<td style="padding-bottom:5px;">
							<input size="6" maxlength="6" name="height" type="text" value="'.htmlentities($this->getFieldValue($obj, 'height'), ENT_COMPAT, 'UTF-8').'" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" /> '.Configuration::get('PS_DIMENSION_UNIT').'
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Deep ( package ) :').'</td>
						<td style="padding-bottom:5px;">
							<input size="6" maxlength="6" name="depth" type="text" value="'.htmlentities($this->getFieldValue($obj, 'depth'), ENT_COMPAT, 'UTF-8').'" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" /> '.Configuration::get('PS_DIMENSION_UNIT').'
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Weight ( package ) :').'</td>
						<td style="padding-bottom:5px;">
							<input size="6" maxlength="6" name="weight" type="text" value="'.htmlentities($this->getFieldValue($obj, 'weight'), ENT_COMPAT, 'UTF-8').'" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" /> '.Configuration::get('PS_WEIGHT_UNIT').'
						</td>
					</tr>
				</table>
				<table cellpadding="5" style="width: 40%; float: left; margin-left: 10px;">
					<tr>
						<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Status:').'</td>
						<td style="padding-bottom:5px;">
							<input style="float:left;" onclick="toggleDraftWarning(false);showOptions(true);" type="radio" name="active" id="active_on" value="1" '.($this->getFieldValue($obj, 'active') ? 'checked="checked" ' : '').'/>
							<label for="active_on" class="t"><img src="../img/admin/enabled.gif" alt="'.$this->l('Enabled').'" title="'.$this->l('Enabled').'" style="float:left; padding:0px 5px 0px 5px;" />'.$this->l('Enabled').'</label>
							<br class="clear" />
							<input style="float:left;" onclick="toggleDraftWarning(true);showOptions(false);"  type="radio" name="active" id="active_off" value="0" '.(!$this->getFieldValue($obj, 'active') ? 'checked="checked" ' : '').'/>
							<label for="active_off" class="t"><img src="../img/admin/disabled.gif" alt="'.$this->l('Disabled').'" title="'.$this->l('Disabled').'" style="float:left; padding:0px 5px 0px 5px" />'.$this->l('Disabled').($obj->active ? '' : ' (<a href="'.$preview_url.'" alt="" target="_blank">'.$this->l('View product in shop').'</a>)').'</label>
						</td>
					</tr>
					<tr id="shop_association">
					<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;'.(!Shop::isMultiShopActivated() ? 'display:none;' : '').'">'.$this->l('Shop association:').'</td><td style="padding-bottom:5px;">';
					$this->displayAssoShop();
					echo '</td>
					</tr>
					<tr id="product_options" '.(!$obj->active ? 'style="display:none"' : '').'>
						<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Options:').'</td>
						<td style="padding-bottom:5px;">
							<input style="float: left;" type="checkbox" name="available_for_order" id="available_for_order" value="1" '.($this->getFieldValue($obj, 'available_for_order') ? 'checked="checked" ' : '').' onclick="if ($(this).is(\':checked\')){$(\'#show_price\').attr(\'checked\', \'checked\');$(\'#show_price\').attr(\'disabled\', \'disabled\');}else{$(\'#show_price\').attr(\'disabled\', \'\');}"/>
							<label for="available_for_order" class="t"><img src="../img/admin/products.gif" alt="'.$this->l('available for order').'" title="'.$this->l('available for order').'" style="float:left; padding:0px 5px 0px 5px" />'.$this->l('available for order').'</label>
							<br class="clear" />
							<input style="float: left;" type="checkbox" name="show_price" id="show_price" value="1" '.($this->getFieldValue($obj, 'show_price') ? 'checked="checked" ' : '').' />
							<label for="show_price" class="t"><img src="../img/admin/gold.gif" alt="'.$this->l('display price').'" title="'.$this->l('show price').'" style="float:left; padding:0px 5px 0px 5px" />'.$this->l('show price').'</label>
							<br class="clear" />
							<input style="float: left;" type="checkbox" name="online_only" id="online_only" value="1" '.($this->getFieldValue($obj, 'online_only') ? 'checked="checked" ' : '').' />
							<label for="online_only" class="t"><img src="../img/admin/basket_error.png" alt="'.$this->l('online only').'" title="'.$this->l('online only').'" style="float:left; padding:0px 5px 0px 5px" />'.$this->l('online only (not sold in store)').'</label>
						</td>
					</tr>
					<tr>
						<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Condition:').'</td>
						<td style="padding-bottom:5px;">
							<select name="condition" id="condition">
								<option value="new" '.($obj->condition == 'new' ? 'selected="selected"' : '').'>'.$this->l('New').'</option>
								<option value="used" '.($obj->condition == 'used' ? 'selected="selected"' : '').'>'.$this->l('Used').'</option>
								<option value="refurbished" '.($obj->condition == 'refurbished' ? 'selected="selected"' : '').'>'.$this->l('Refurbished').'</option>
							</select>
						</td>
					</tr>
					<tr>
						<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Manufacturer:').'</td>
						<td style="padding-bottom:5px;">
							<select name="id_manufacturer" id="id_manufacturer">
								<option value="0">-- '.$this->l('Choose (optional)').' --</option>';
		if ($id_manufacturer = $this->getFieldValue($obj, 'id_manufacturer'))
			echo '				<option value="'.$id_manufacturer.'" selected="selected">'.Manufacturer::getNameById($id_manufacturer).'</option>
								<option disabled="disabled">----------</option>';
		echo '
							</select>&nbsp;&nbsp;&nbsp;<a href="?tab=AdminManufacturers&addmanufacturer&token='.Tools::getAdminToken('AdminManufacturers'.(int)(Tab::getIdFromClassName('AdminManufacturers')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('Are you sure you want to delete product information entered?', __CLASS__, true, false).'\');"><img src="../img/admin/add.gif" alt="'.$this->l('Create').'" title="'.$this->l('Create').'" /> <b>'.$this->l('Create').'</b></a>
						</td>
					</tr>
					<tr>
						<td style="vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Supplier:').'</td>
						<td style="padding-bottom:5px;">
							<select name="id_supplier" id="id_supplier">
								<option value="0">-- '.$this->l('Choose (optional)').' --</option>';
		if ($id_supplier = $this->getFieldValue($obj, 'id_supplier'))
			echo '				<option value="'.$id_supplier.'" selected="selected">'.Supplier::getNameById($id_supplier).'</option>
								<option disabled="disabled">----------</option>';
		echo '
							</select>&nbsp;&nbsp;&nbsp;<a href="?tab=AdminSuppliers&addsupplier&token='.Tools::getAdminToken('AdminSuppliers'.(int)(Tab::getIdFromClassName('AdminSuppliers')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('Are you sure you want to delete entered product information?', __CLASS__, true, false).'\');"><img src="../img/admin/add.gif" alt="'.$this->l('Create').'" title="'.$this->l('Create').'" /> <b>'.$this->l('Create').'</b></a>
						</td>
					</tr>
				</table>
				<div class="clear"></div>
				<table cellpadding="5" style="width: 100%;">
					<tr><td colspan="2"><hr style="width:100%;" /></td></tr>';
					$this->displayPack($obj);
		echo '		<tr><td colspan="2"><hr style="width:100%;" /></td></tr>';

/*
 * Form for add a virtual product like software, mp3, etc...
 */
	$productDownload = new ProductDownload();
	if ($id_product_download = $productDownload->getIdFromIdProduct($this->getFieldValue($obj, 'id')))
		$productDownload = new ProductDownload($id_product_download);

?>
    <script type="text/javascript">
    // <![CDATA[
    	ThickboxI18nImage = '<?php echo $this->l('Image') ?>';
    	ThickboxI18nOf = '<?php echo $this->l('of') ?>';
    	ThickboxI18nClose = '<?php echo $this->l('Close') ?>';
    	ThickboxI18nOrEscKey = '<?php echo $this->l('(or "Esc")') ?>';
    	ThickboxI18nNext = '<?php echo $this->l('Next >') ?>';
    	ThickboxI18nPrev = '<?php echo $this->l('< Previous') ?>';
    	tb_pathToImage = '../img/loadingAnimation.gif';
    //]]>
    </script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>jquery/thickbox-modified.js"></script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>jquery/ajaxfileupload.js"></script>
	<script type="text/javascript" src="<?php echo _PS_JS_DIR_ ?>date.js"></script>
	<style type="text/css">
		<!--
		@import url(<?php echo _PS_CSS_DIR_?>thickbox.css);
		-->
	</style>
	<script type="text/javascript">
	//<![CDATA[
	function toggleVirtualProduct(elt)
	{
		if (elt.checked)
		{
			$('#virtual_good').show('slow');
			$('#virtual_good_more').show('slow');
			getE('out_of_stock_1').checked = 'checked';
			getE('out_of_stock_2').disabled = 'disabled';
			getE('out_of_stock_3').disabled = 'disabled';
			getE('label_out_of_stock_2').setAttribute('for', '');
			getE('label_out_of_stock_3').setAttribute('for', '');
		}
		else
		{
			$('#virtual_good').hide('slow');
			$('#virtual_good_more').hide('slow');
			getE('out_of_stock_2').disabled = false;
			getE('out_of_stock_3').disabled = false;
			getE('label_out_of_stock_2').setAttribute('for', 'out_of_stock_2');
			getE('label_out_of_stock_3').setAttribute('for', 'out_of_stock_3');
		}
	}

	function uploadFile()
	{
		$.ajaxFileUpload (
			{
				url:'./uploadProductFile.php',
				secureuri:false,
				fileElementId:'virtual_product_file',
				dataType: 'xml',

				success: function (data, status)
				{
					data = data.getElementsByTagName('return')[0];
					var result = data.getAttribute("result");
					var msg = data.getAttribute("msg");
					var fileName = data.getAttribute("filename");

					if (result == "error")
					{
						$("#upload-confirmation").html('<p>error: ' + msg + '</p>');
					}
					else
					{
						$('#virtual_product_file').remove();
						$('#virtual_product_file_label').hide();
						$('#file_missing').hide();
						$('#delete_downloadable_product').show();
						$('#virtual_product_name').attr('value', fileName);
						$('#upload-confirmation').html(
							'<a class="link" href="get-file-admin.php?file='+msg+'&filename='+fileName+'"><?php echo $this->l('The file') ?>&nbsp;"' + fileName + '"&nbsp;<?php echo $this->l('has successfully been uploaded') ?></a>' +
							'<input type="hidden" id="virtual_product_filename" name="virtual_product_filename" value="' + msg + '" />');
					}
				}
			}
		);
	}

	//]]>
	</script>
	<?php
		echo '
		<script type="text/javascript" src="'._PS_JS_DIR_.'price.js"></script>
		<script type="text/javascript">
			var newLabel = \''.$this->l('New label').'\';
			var choose_language = \''.$this->l('Choose language:').'\';
			var required = \''.$this->l('required').'\';
			var customizationUploadableFileNumber = '.(int)($this->getFieldValue($obj, 'uploadable_files')).';
			var customizationTextFieldNumber = '.(int)($this->getFieldValue($obj, 'text_fields')).';
			var uploadableFileLabel = 0;
			var textFieldLabel = 0;
		</script>
	<tr>
		<td colspan="2">

			<p><input type="checkbox" id="is_virtual_good" name="is_virtual_good" value="true" onclick="toggleVirtualProduct(this);" '.$check.' />
			<label for="is_virtual_good" class="t bold" style="color: black;">'.$this->l('Is this a virtual product?').'</label></p>
			<div id="virtual_good" '.$hidden.'>
			
			<br/>'.$this->l('Does this product has an associated file ?').'<br/>';
			
			$exists_file = realpath(_PS_DOWNLOAD_DIR_).'/'.$productDownload->filename;

			if ($productDownload->id && file_exists($exists_file) || !empty($productDownload->display_filename))
			{			
				echo '<input type="radio" value="1" id="virtual_good_file_1" name="is_virtual_file" checked="checked" />'. $this->l('Yes').'
				<input type="radio" value="0" id="virtual_good_file_2" name="is_virtual_file" />'.$this->l('No').'<br /><br />'; 
			}
			else
			{
				echo '<input type="radio" value="1" id="virtual_good_file_1" name="is_virtual_file" />'. $this->l('Yes').'
				<input type="radio" value="0" id="virtual_good_file_2" name="is_virtual_file" checked="checked" />'.$this->l('No').'<br /><br />'; 
			}
			
			if (!file_exists($exists_file) && !empty($productDownload->display_filename) && empty($cache_default_attribute))
			{
				$msg = sprintf(Tools::displayError('This file "%s" is missing'), $productDownload->display_filename);
				echo '<p class="alert" id="file_missing">
					<b>'.$msg.' :<br/>
					'.realpath(_PS_DOWNLOAD_DIR_) .'/'. $productDownload->filename.'</b>
				</p>';
			}			

			if (!ProductDownload::checkWritableDir())
			{	
				echo '<p class="alert">
					'.$this->l('Your download repository is not writable.').'<br/>
					'.realpath(_PS_DOWNLOAD_DIR_).'
				</p>';
			}
			
			echo '<div id="is_virtual_file_product" style="display:none;">';
			if (empty($cache_default_attribute))
			{
				if($productDownload->id) 
					echo '<input type="hidden" id="virtual_product_id" name="virtual_product_id" value="'.$productDownload->id.'" />';
				
					echo '<p class="block">';
			
					if (!$productDownload->checkFile())
					{
						echo '<div style="padding:5px;width:50%;float:left;margin-right:20px;border-right:1px solid #E0D0B1">
						<p>'.$this->l('Your server\'s maximum upload file size is') . ':&nbsp;' . ini_get('upload_max_filesize').'</p>';
						if (!strval(Tools::getValue('virtual_product_filename')) OR $productDownload->id > 0)
						{
							echo '<label id="virtual_product_file_label" for="virtual_product_file" class="t">'.$this->l('Upload a file').'</label>
							<p><input type="file" id="virtual_product_file" name="virtual_product_file" onchange="uploadFile();" maxlength="'.$this->maxFileSize.'" /></p>';
						}
						
						echo '<div id="upload-confirmation">';
							if ($up_filename = strval(Tools::getValue('virtual_product_filename')))
								echo '<input type="hidden" id="virtual_product_filename" name="virtual_product_filename" value="'.$up_filename.'" />';
					
						echo '</div>
							<a id="delete_downloadable_product" style="display:none;" onclick="return confirm(\''.addslashes($this->l('Delete this file')).'\')" href="'.$_SERVER['REQUEST_URI'].'&deleteVirtualProduct=true'.'" class="red">'.$this->l('Delete this file').'</a>';
					}
					else
					{
						echo '<input type="hidden" id="virtual_product_filename" name="virtual_product_filename" value="'.$productDownload->filename.'" />
						'.$this->l('This is the link').':&nbsp;'.$productDownload->getHtmlLink(false, true).'
						<a onclick="return confirm(\''.addslashes($this->l('Delete this file')).'\')" href="'.$_SERVER['REQUEST_URI'].'&deleteVirtualProduct=true'.'" class="red">'.$this->l('Delete this file').'</a>';
					}
					
					$display_filename = ($productDownload->id > 0) ? $productDownload->display_filename : htmlentities(Tools::getValue('virtual_product_name'), ENT_COMPAT, 'UTF-8');
					echo '</p><p class="block">
						<label for="virtual_product_name" class="t">'.$this->l('Filename').'</label>
						<input type="text" id="virtual_product_name" name="virtual_product_name" style="width:200px" value="'.$display_filename.'" />
						<span class="hint" name="help_box" style="display:none;">'.$this->l('The full filename with its extension (e.g., Book.pdf)').'</span>
					</p>
			
					</div>';
					
					if (!$productDownload->id || !$productDownload->active) 
						$hidden = 'display:none;';
						
					$nb_downloadable = ($productDownload->id > 0) ? $productDownload->nb_downloadable : htmlentities(Tools::getValue('virtual_product_nb_downloable'), ENT_COMPAT, 'UTF-8');
					$date_expiration = ($productDownload->id > 0) ? ((!empty($productDownload->date_expiration) && $productDownload->date_expiration != '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($productDownload->date_expiration)) : '' ) : htmlentities(Tools::getValue('virtual_product_expiration_date'), ENT_COMPAT, 'UTF-8');
					$nb_days_accessible = ($productDownload->id > 0) ? $productDownload->nb_days_accessible : htmlentities(Tools::getValue('virtual_product_nb_days'), ENT_COMPAT, 'UTF-8');
					$is_shareable = ($productDownload->id > 0 && $productDownload->is_shareable) ? 'checked="checked"' : '';
						
					echo '<div id="virtual_good_more" style="'.$hidden.'padding:5px;width:40%;float:left;margin-left:10px">
							<p class="block">
								<label for="virtual_product_nb_downloable" class="t">'.$this->l('Number of downloads').'</label>
								<input type="text" id="virtual_product_nb_downloable" name="virtual_product_nb_downloable" value="'.$nb_downloadable.'" class="" size="6" />
								<span class="hint" name="help_box" style="display:none">'.$this->l('Number of authorized downloads per customer').'</span>
							</p>
							<p class="block">
								<label for="virtual_product_expiration_date" class="t">'.$this->l('Expiration date').'</label>
								<input type="text" id="virtual_product_expiration_date" name="virtual_product_expiration_date" value="'.$date_expiration.'" size="11" maxlength="10" autocomplete="off" /> '.$this->l('Format: YYYY-MM-DD').'
								<span class="hint" name="help_box" style="display:none">'.$this->l('No expiration date if you leave this blank').'</span>
							</p>
							<p class="block">
								<label for="virtual_product_nb_days" class="t">'.$this->l('Number of days').'</label>
								<input type="text" id="virtual_product_nb_days" name="virtual_product_nb_days" value="'.$nb_days_accessible.'" class="" size="4" /><sup> *</sup>
								<span class="hint" name="help_box" style="display:none">'.$this->l('How many days this file can be accessed by customers').' - <em>('.$this->l('set to zero for unlimited access').')</em></span>
							</p>						
							<p class="block">
								<label for="virtual_product_is_shareable" class="t">'.$this->l('is shareable').'</label>
								<input type="checkbox" id="virtual_product_is_shareable" name="virtual_product_is_shareable" value="1" '.$is_shareable.'/>
								<span class="hint" name="help_box" style="display:none">'.$this->l('Specify if the file can be shared').'</span>
							</p>
					</div>
				';				
			}
			else 
			{
				$error ='';
				echo '<div class="hint clear" style="display: block;width: 70%;">'.$this->l('You used combinations, for this reason you can\'t edit your file here, but in the Combinations tab').'</div>
				<br />';
				$product_attribute = ProductDownload::getAttributeFromIdProduct($this->getFieldValue($obj, 'id'));
				foreach ($product_attribute as $product)
				{
					$productDownloadAttribute = new ProductDownload($product['id_product_download']);
					$exists_file2 = realpath(_PS_DOWNLOAD_DIR_).'/'.$productDownloadAttribute->filename;
					if (!file_exists($exists_file2))
					{
						$msg = sprintf(Tools::displayError('This file "%s" is missing'), $productDownloadAttribute->display_filename);
						$error .= '<p class="alert" id="file_missing">
							<b>'.$msg.' :<br/>
							'.realpath(_PS_DOWNLOAD_DIR_) .'/'. $productDownloadAttribute->filename.'</b>
						</p>';
					}
				}
				echo $error;
			}
			echo '</div>
			</div>
		</td>
	</tr>';
	
	includeDatepicker('virtual_product_expiration_date');
	
	echo '<tr>
				<td colspan="2" style="padding-bottom:5px;"><hr style="width:100%;" /></td>
		 </tr>
		<script type="text/javascript">
			$(document).ready(function(){
				if ($("#is_virtual_good").attr("checked"))
				{
					$("#virtual_good").show("slow");
					$("#virtual_good_more").show("slow");
				}

				if ( $("input[name=is_virtual_file]:checked").val() == 1)
				{
					$("#virtual_good_attributes").show();
					$("#is_virtual_file_product").show();
				}
				else
				{
					$("#virtual_good_attributes").hide();
					$("#is_virtual_file_product").hide();
				}
				
				$("input[name=is_virtual_file]").live("change", function() {
					if($(this).val() == "1")
					{
						$("#virtual_good_attributes").show();
						$("#is_virtual_file_product").show();
					}
					else
					{
						$("#virtual_good_attributes").hide();
						$("#is_virtual_file_product").hide();
					}
				});
			});
		</script>
					<tr>
						<td class="col-left">'.$this->l('Pre-tax wholesale price:').'</td>
						<td style="padding-bottom:5px;">
							'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<input size="11" maxlength="14" name="wholesale_price" type="text" value="'.htmlentities($this->getFieldValue($obj, 'wholesale_price'), ENT_COMPAT, 'UTF-8').'" onchange="this.value = this.value.replace(/,/g, \'.\');" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').'
							<span style="margin-left:10px">'.$this->l('The wholesale price at which you bought this product').'</span>
						</td>
					</tr>';
					echo '
					<tr>
						<td class="col-left">'.$this->l('Pre-tax retail price:').'</td>
						<td style="padding-bottom:5px;">
							'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<input size="11" maxlength="14" id="priceTE" name="price" type="text" value="'.$this->getFieldValue($obj, 'price').'" onchange="this.value = this.value.replace(/,/g, \'.\');" onkeyup="if (isArrowKey(event)) return; calcPriceTI();" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').'<sup> *</sup>
							<span style="margin-left:2px">'.$this->l('The pre-tax retail price to sell this product').'</span>
						</td>
					</tr>';
					$tax_rules_groups = TaxRulesGroup::getTaxRulesGroups(true);
					$taxesRatesByGroup = TaxRulesGroup::getAssociatedTaxRatesByIdCountry($this->context->country->id);
					$ecotaxTaxRate = Tax::getProductEcotaxRate();
					echo '<script type="text/javascript">';
					echo 'noTax = '.(Tax::excludeTaxeOption() ? 'true' : 'false'), ";\n";
					echo 'taxesArray = new Array ();'."\n";
					echo 'taxesArray[0] = 0', ";\n";

					foreach ($tax_rules_groups as $tax_rules_group)
					{
    					$tax_rate = (array_key_exists($tax_rules_group['id_tax_rules_group'], $taxesRatesByGroup) ?  $taxesRatesByGroup[$tax_rules_group['id_tax_rules_group']] : 0);
						echo 'taxesArray['.$tax_rules_group['id_tax_rules_group'].']='.$tax_rate."\n";
					}
					echo '
						ecotaxTaxRate = '.($ecotaxTaxRate / 100).';
					</script>';
					echo '
					<tr>
						<td class="col-left">'.$this->l('Tax rule:').'</td>
						<td style="padding-bottom:5px;">
					<span '.(Tax::excludeTaxeOption() ? 'style="display:none;"' : '' ).'>
					 <select onChange="javascript:calcPriceTI(); unitPriceWithTax(\'unit\');" name="id_tax_rules_group" id="id_tax_rules_group" '.(Tax::excludeTaxeOption() ? 'disabled="disabled"' : '' ).'>
					     <option value="0">'.$this->l('No Tax').'</option>';

						foreach ($tax_rules_groups as $tax_rules_group)
							echo '<option value="'.$tax_rules_group['id_tax_rules_group'].'" '.(($this->getFieldValue($obj, 'id_tax_rules_group') == $tax_rules_group['id_tax_rules_group']) ? ' selected="selected"' : '').'>'.Tools::htmlentitiesUTF8($tax_rules_group['name']).'</option>';

				echo '</select>

				<a href="?tab=AdminTaxRulesGroup&addtax_rules_group&token='.Tools::getAdminToken('AdminTaxRulesGroup'.(int)(Tab::getIdFromClassName('AdminTaxRulesGroup')).(int)$this->context->employee->id).'&id_product='.(int)$obj->id.'" onclick="return confirm(\''.$this->l('Are you sure you want to delete entered product information?', __CLASS__, true, false).'\');"><img src="../img/admin/add.gif" alt="'.$this->l('Create').'" title="'.$this->l('Create').'" /> <b>'.$this->l('Create').'</b></a></span>
				';
				if (Tax::excludeTaxeOption())
				{
					echo '<span style="margin-left:10px; color:red;">'.$this->l('Taxes are currently disabled').'</span> (<b><a href="index.php?tab=AdminTaxes&token='.Tools::getAdminToken('AdminTaxes'.(int)(Tab::getIdFromClassName('AdminTaxes')).(int)$this->context->employee->id).'">'.$this->l('Tax options').'</a></b>)';
					echo '<input type="hidden" value="'.(int)($this->getFieldValue($obj, 'id_tax_rules_group')).'" name="id_tax_rules_group" />';
				}

				echo '</td>
					</tr>
				';
				if (Configuration::get('PS_USE_ECOTAX'))
					echo '
					<tr>
						<td class="col-left">'.$this->l('Eco-tax (tax incl.):').'</td>
						<td style="padding-bottom:5px;">
							'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<input size="11" maxlength="14" id="ecotax" name="ecotax" type="text" value="'.$this->getFieldValue($obj, 'ecotax').'" onkeyup="if (isArrowKey(event))return; calcPriceTE(); this.value = this.value.replace(/,/g, \'.\'); if (parseInt(this.value) > getE(\'priceTE\').value) this.value = getE(\'priceTE\').value; if (isNaN(this.value)) this.value = 0;" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').'
							<span style="margin-left:10px">('.$this->l('already included in price').')</span>
						</td>
					</tr>';

				if ($this->context->country->display_tax_label)
				{
					echo '
						<tr '.(Tax::excludeTaxeOption() ? 'style="display:none"' : '' ).'>
							<td class="col-left">'.$this->l('Retail price with tax:').'</td>
							<td style="padding-bottom:5px;">
								'.($currency->format % 2 != 0 ? ' '.$currency->sign : '').' <input size="11" maxlength="14" id="priceTI" type="text" value="" onchange="noComma(\'priceTI\');" onkeyup="if (isArrowKey(event)) return;  calcPriceTE();" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').'
							</td>
						</tr>';
				} 
				else 
					echo '<input size="11" maxlength="14" id="priceTI" type="hidden" value="" onchange="noComma(\'priceTI\');" onkeyup="if (isArrowKey(event)) return;  calcPriceTE();" />';

				echo '
					<tr id="tr_unit_price">
						<td class="col-left">'.$this->l('Unit price without tax:').'</td>
						<td style="padding-bottom:5px;">
							'.($currency->format % 2 != 0 ? ' '.$currency->sign : '').' <input size="11" maxlength="14" id="unit_price" name="unit_price" type="text" value="'.($this->getFieldValue($obj, 'unit_price_ratio') != 0 ? Tools::ps_round($this->getFieldValue($obj, 'price') / $this->getFieldValue($obj, 'unit_price_ratio'), 2) : 0).'" onkeyup="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\'); unitPriceWithTax(\'unit\');"/>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' '.$this->l('per').' <input size="6" maxlength="10" id="unity" name="unity" type="text" value="'.htmlentities($this->getFieldValue($obj, 'unity'), ENT_QUOTES, 'UTF-8').'" onkeyup="if (isArrowKey(event)) return ;unitySecond();" onchange="unitySecond();"/>'.
							(Configuration::get('PS_TAX') && $this->context->country->display_tax_label ? '<span style="margin-left:15px">'.$this->l('or').' '.($currency->format % 2 != 0 ? ' '.$currency->sign : '').'<span id="unit_price_with_tax">0.00</span>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' '.$this->l('per').' <span id="unity_second">'.$this->getFieldValue($obj, 'unity').'</span> '.$this->l('with tax') : '').'</span>
							<p>'.$this->l('Eg. $15 per Lb').'</p>
						</td>
					</tr>
					<tr>
						<td class="col-left">&nbsp;</td>
						<td style="padding-bottom:5px;">
							<input type="checkbox" name="on_sale" id="on_sale" style="padding-top: 5px;" '.($this->getFieldValue($obj, 'on_sale') ? 'checked="checked"' : '').'value="1" />&nbsp;<label for="on_sale" class="t">'.$this->l('Display "on sale" icon on product page and text on product listing').'</label>
						</td>
					</tr>
					<tr>
						<td class="col-left"><b>'.$this->l('Final retail price:').'</b></td>
						<td style="padding-bottom:5px;">
							<span style="'.($this->context->country->display_tax_label ? '' : 'display:none').'">
							'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<span id="finalPrice" style="font-weight: bold;"></span>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').'<span'.(!Configuration::get('PS_TAX') ? ' style="display:none;"' : '').'> ('.$this->l('tax incl.').')</span>
							</span>
							<span'.(!Configuration::get('PS_TAX') ? ' style="display:none;"' : '').'>';

							if ($this->context->country->display_tax_label)
								echo ' / ';

							 echo ($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<span id="finalPriceWithoutTax" style="font-weight: bold;"></span>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' '.($this->context->country->display_tax_label ? '('.$this->l('tax excl.').')' : '').'</span>
						</td>
					</tr>
					<tr>
						<td class="col-left">&nbsp;</td>
						<td>
							<div class="hint clear" style="display: block;width: 70%;">'.$this->l('You can define many discounts and specific price rules in the Prices tab').'</div>
						</td>
					</tr>
					<tr><td colspan="2" style="padding-bottom:5px;"><hr style="width:100%;" /></td></tr>';


				if ((int)Configuration::get('PS_STOCK_MANAGEMENT'))
				{

					if (!$has_attribute)
					{
						if ($obj->id)
						{
							echo '
							<tr><td class="col-left">'.$this->l('Stock Movement:').'</td>
								<td style="padding-bottom:5px;">
									<select id="id_mvt_reason" name="id_mvt_reason">
										<option value="-1">--</option>';
							$reasons = StockMvtReason::getStockMvtReasons($this->context->language->id);

							foreach ($reasons as $reason)
								echo '<option rel="'.$reason['sign'].'" value="'.$reason['id_stock_mvt_reason'].'" '.(Configuration::get('PS_STOCK_MVT_REASON_DEFAULT') == $reason['id_stock_mvt_reason'] ? 'selected="selected"' : '').'>'.$reason['name'].'</option>';
							echo '</select>
									<input id="mvt_quantity" type="text" name="mvt_quantity" size="3" maxlength="6" value="0"/>&nbsp;&nbsp;
									<span style="display:none;" id="mvt_sign"></span>
								</td>
							</tr>
							<tr>
								<td class="col-left">&nbsp;</td>
								<td>
									<div class="hint clear" style="display: block;width: 70%;">'.$this->l('Choose the reason and enter the quantity that you want to increase or decrease in your stock').'</div>
								</td>
							</tr>';
						}
						else
							echo '<tr><td class="col-left">'.$this->l('Initial stock:').'</td>
									<td style="padding-bottom:5px;">
										<input size="3" maxlength="6" name="quantity" type="text" value="0" />
									</td>';
						echo  '<tr>
								<td class="col-left">'.$this->l('Minimum quantity:').'</td>
									<td style="padding-bottom:5px;">
										<input size="3" maxlength="6" name="minimal_quantity" id="minimal_quantity" type="text" value="'.($this->getFieldValue($obj, 'minimal_quantity') ? $this->getFieldValue($obj, 'minimal_quantity') : 1).'" />
										<p>'.$this->l('The minimum quantity to buy this product (set to 1 to disable this feature)').'</p>
									</td>
								</tr>';
					}

				if ($obj->id)
					echo '
						<tr><td class="col-left">'.$this->l('Quantity in stock:').'</td>
							<td style="padding-bottom:5px;"><b>'.$qty.'</b><input type="hidden" name="quantity" value="'.$qty.'" /></td>
						</tr>
					';
				if ($has_attribute)
					echo '<tr>
							<td class="col-left">&nbsp;</td>
							<td>
								<div class="hint clear" style="display: block;width: 70%;">'.$this->l('You used combinations, for this reason you can\'t edit your stock quantity here, but in the Combinations tab').'</div>
							</td>
						</tr>';
				}
				else
				{
					echo '<tr>
							<td colspan="2">'.$this->l('The stock management is disabled').'</td>
						</tr>';

				echo '
						<tr>
							<td class="col-left">'.$this->l('Minimum quantity:').'</td>
							<td style="padding-bottom:5px;">
								<input size="3" maxlength="6" name="minimal_quantity" id="minimal_quantity" type="text" value="'.($this->getFieldValue($obj, 'minimal_quantity') ? $this->getFieldValue($obj, 'minimal_quantity') : 1).'" />
								<p>'.$this->l('The minimum quantity to buy this product (set to 1 to disable this feature)').'</p>
							</td>
						</tr>
					';
				}
							
				echo '
					<tr><td colspan="2" style="padding-bottom:5px;"><hr style="width:100%;" /></td></tr>
					<tr>
						<td class="col-left">'.$this->l('Additional shipping cost:').'</td>
						<td style="padding-bottom:5px;">
							<input type="text" name="additional_shipping_cost" value="'.($this->getFieldValue($obj, 'additional_shipping_cost')).'" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '');
							if ($this->context->country->display_tax_label)
								echo ' ('.$this->l('tax excl.').')';

					echo '<p>'.$this->l('Carrier tax will be applied.').'</p>
						</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Displayed text when in-stock:').'</td>
						<td style="padding-bottom:5px;" class="translatable">';
		foreach ($this->_languages as $language)
			echo '		<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
								<input size="30" type="text" id="available_now_'.$language['id_lang'].'" name="available_now_'.$language['id_lang'].'"
								value="'.stripslashes(htmlentities($this->getFieldValue($obj, 'available_now', $language['id_lang']), ENT_COMPAT, 'UTF-8')).'" />
								<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
							</div>';
		echo '			</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Displayed text when allowed to be back-ordered:').'</td>
						<td style="padding-bottom:5px;" class="translatable">';
		foreach ($this->_languages as $language)
			echo '		<div  class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
								<input size="30" type="text" id="available_later_'.$language['id_lang'].'" name="available_later_'.$language['id_lang'].'"
								value="'.stripslashes(htmlentities($this->getFieldValue($obj, 'available_later', $language['id_lang']), ENT_COMPAT, 'UTF-8')).'" />
								<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
							</div>';
			echo '	</td>
					</tr>';

			// Check if product has combination, to display the available date only for the product or for each combination
			if (Combination::isFeatureActive())
				$countAttributes = (int)Db::getInstance()->getValue('SELECT COUNT(id_product) FROM '._DB_PREFIX_.'product_attribute WHERE id_product = '.(int)$obj->id);

			if (isset($countAttributes) && $countAttributes == 0)
			{
				echo '
						<tr>
							<td class="col-left">'.$this->l('Available date:').'</td>
							<td style="padding-bottom:5px;">
							<input id="available_date" name="available_date" value="'.(($this->getFieldValue($obj, 'available_date') != 0) ? stripslashes(htmlentities(Tools::displayDate($this->getFieldValue($obj, 'available_date'), $language['id_lang']))) : '0000-00-00').'" style="text-align: center;" type="text" />
								<p>'.$this->l('The available date when this product is out of stock').'</p>
						</td>
						</tr>';
				// date picker include
				includeDatepicker('available_date');
			}

			echo '
					<script type="text/javascript">
						calcPriceTI();
					</script>

					<tr>
						<td class="col-left">'.$this->l('When out of stock:').'</td>
						<td style="padding-bottom:5px;">
							<input type="radio" name="out_of_stock" id="out_of_stock_1" value="0" '.((int)($this->getFieldValue($obj, 'out_of_stock')) == 0 ? 'checked="checked"' : '').'/> <label for="out_of_stock_1" class="t" id="label_out_of_stock_1">'.$this->l('Deny orders').'</label>
							<br /><input type="radio" name="out_of_stock" id="out_of_stock_2" value="1" '.($this->getFieldValue($obj, 'out_of_stock') == 1 ? 'checked="checked"' : '').'/> <label for="out_of_stock_2" class="t" id="label_out_of_stock_2">'.$this->l('Allow orders').'</label>
							<br /><input type="radio" name="out_of_stock" id="out_of_stock_3" value="2" '.($this->getFieldValue($obj, 'out_of_stock') == 2 ? 'checked="checked"' : '').'/> <label for="out_of_stock_3" class="t" id="label_out_of_stock_3">'.$this->l('Default:').' <i>'.$this->l(((int)(Configuration::get('PS_ORDER_OUT_OF_STOCK')) ? 'Allow orders' : 'Deny orders')).'</i> ('.$this->l('as set in').' <a href="index.php?tab=AdminPPreferences&token='.Tools::getAdminToken('AdminPPreferences'.(int)(Tab::getIdFromClassName('AdminPPreferences')).(int)$this->context->employee->id).'"  onclick="return confirm(\''.$this->l('Are you sure you want to delete entered product information?', __CLASS__, true, false).'\');">'.$this->l('Preferences').'</a>)</label>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding-bottom:5px;">
							<hr style="width:100%;" />
						</td>
					</tr>
					<tr>
						<td class="col-left"><label for="id_category_default" class="t">'.$this->l('Default category:').'</label></td>
						<td>
						<div id="no_default_category" style="color: red;font-weight: bold;display: none;">'.$this->l('Please check a category in order to select the default category.').'</div>
						<script type="text/javascript">
							var post_selected_cat;
						</script>';
						$default_category = Tools::getValue('id_category', 1);
						if (!$obj->id)
						{
							$selectedCat = Category::getCategoryInformations(Tools::getValue('categoryBox', array($default_category)), $this->_defaultFormLanguage);
							echo '
							<script type="text/javascript">
								post_selected_cat = \''.implode(',', array_keys($selectedCat)).'\';
							</script>';
						}
						else
						{
							if (Tools::isSubmit('categoryBox'))
								$selectedCat = Category::getCategoryInformations(Tools::getValue('categoryBox', array($default_category)), $this->_defaultFormLanguage);
							else
							$selectedCat = Product::getProductCategoriesFull($obj->id, $this->_defaultFormLanguage);
						}

						echo '<select id="id_category_default" name="id_category_default">';
							foreach ($selectedCat as $cat)
								echo '<option value="'.$cat['id_category'].'" '.($obj->id_category_default == $cat['id_category'] ? 'selected' : '').'>'.$cat['name'].'</option>';
						echo '</select>
						</td>
					</tr>
					<tr id="tr_categories">
						<td colspan="2">
						';
					// Translations are not automatic for the moment ;)
					$trads = array(
						 'Home' => $this->l('Home'),
						 'selected' => $this->l('selected'),
						 'Collapse All' => $this->l('Collapse All'),
						 'Expand All' => $this->l('Expand All'),
						 'Check All' => $this->l('Check All'),
						 'Uncheck All'  => $this->l('Uncheck All')
					);
					echo Helper::renderAdminCategorieTree($trads, $selectedCat).'
						</td>
					</tr>
					<tr><td colspan="2" style="padding-bottom:5px;"><hr style="width:100%;" /></td></tr>
					<tr><td colspan="2">
						<span onclick="$(\'#seo\').slideToggle();" style="cursor: pointer"><img src="../img/admin/arrow.gif" alt="'.$this->l('SEO').'" title="'.$this->l('SEO').'" style="float:left; margin-right:5px;"/>'.$this->l('Click here to improve product\'s rank in search engines (SEO)').'</span><br />
						<div id="seo" style="display: none; padding-top: 15px;">
							<table>
								<tr>
									<td class="col-left">'.$this->l('Meta title:').'</td>
									<td class="translatable">';
		foreach ($this->_languages as $language)
			echo '					<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
											<input size="55" type="text" id="meta_title_'.$language['id_lang'].'" name="meta_title_'.$language['id_lang'].'"
											value="'.htmlentities($this->getFieldValue($obj, 'meta_title', $language['id_lang']), ENT_COMPAT, 'UTF-8').'" />
											<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
										</div>';
		echo '						<p class="clear">'.$this->l('Product page title; leave blank to use product name').'</p>
									</td>
								</tr>
								<tr>
									<td class="col-left">'.$this->l('Meta description:').'</td>
									<td class="translatable">';
		foreach ($this->_languages as $language)
			echo '					<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
											<input size="55" type="text" id="meta_description_'.$language['id_lang'].'" name="meta_description_'.$language['id_lang'].'"
											value="'.htmlentities($this->getFieldValue($obj, 'meta_description', $language['id_lang']), ENT_COMPAT, 'UTF-8').'" />
											<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
										</div>';
		echo '						<p class="clear">'.$this->l('A single sentence for HTML header').'</p>
									</td>
								</tr>
								<tr>
									<td class="col-left">'.$this->l('Meta keywords:').'</td>
									<td class="translatable">';
		foreach ($this->_languages as $language)
			echo '					<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
											<input size="55" type="text" id="meta_keywords_'.$language['id_lang'].'" name="meta_keywords_'.$language['id_lang'].'"
											value="'.htmlentities($this->getFieldValue($obj, 'meta_keywords', $language['id_lang']), ENT_COMPAT, 'UTF-8').'" />
											<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' <>;=#{}<span class="hint-pointer">&nbsp;</span></span>
										</div>';
		echo '						<p class="clear">'.$this->l('Keywords for HTML header, separated by a comma').'</p>
									</td>
								</tr>
								<tr>
									<td class="col-left">'.$this->l('Friendly URL:').'</td>
									<td class="translatable">';
		foreach ($this->_languages as $language)
		{
			echo '					<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
											<input size="55" type="text" id="link_rewrite_'.$language['id_lang'].'" name="link_rewrite_'.$language['id_lang'].'"
											value="'.htmlentities($this->getFieldValue($obj, 'link_rewrite', $language['id_lang']), ENT_COMPAT, 'UTF-8').'" onkeyup="if (isArrowKey(event)) return ;updateFriendlyURL();" onchange="updateFriendlyURL();" /><sup> *</sup>
											<span class="hint" name="help_box">'.$this->l('Only letters and the "less" character are allowed').'<span class="hint-pointer">&nbsp;</span></span>
										</div>';
		}
		echo '						<p class="clear" style="padding:10px 0 0 0">'.'<a style="cursor:pointer" class="button" onmousedown="updateFriendlyURLByName();">'.$this->l('Generate').'</a>&nbsp;'.$this->l('Friendly-url from product\'s name.').'<br /><br />';
		echo '						'.$this->l('Product link will look like this:').' '.(Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].'/<b>id_product</b>-<span id="friendly-url"></span>.html</p>
									</td>
								</tr>';
		echo '</td></tr></table>
						</div>
					</td></tr>
					<tr><td colspan="2" style="padding-bottom:5px;"><hr style="width:100%;" /></td></tr>
					<tr>
						<td class="col-left">'.$this->l('Short description:').'<br /><br /><i>('.$this->l('appears in the product lists and on the top of the product page').')</i></td>
						<td style="padding-bottom:5px;" class="translatable">';
		foreach ($this->_languages as $language)
			echo '		<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').';float: left;">
								<textarea class="rte" cols="100" rows="10" id="description_short_'.$language['id_lang'].'" name="description_short_'.$language['id_lang'].'">'.htmlentities(stripslashes($this->getFieldValue($obj, 'description_short', $language['id_lang'])), ENT_COMPAT, 'UTF-8').'</textarea>
							</div>';
		echo '<p class="clear"></p>
			</td>
					</tr>
					<tr>
						<td class="col-left">'.$this->l('Description:').'<br /><br /><i>('.$this->l('appears in the body of the product page').')</i></td>
						<td style="padding-bottom:5px;" class="translatable">';
		foreach ($this->_languages as $language)
			echo '		<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').';float: left;">
								<textarea class="rte" cols="100" rows="20" id="description_'.$language['id_lang'].'" name="description_'.$language['id_lang'].'">'.htmlentities(stripslashes($this->getFieldValue($obj, 'description', $language['id_lang'])), ENT_COMPAT, 'UTF-8').'</textarea>
							</div>';
		echo '<p class="clear"></p>
					</td>
					</tr>';
				$images = Image::getImages($this->context->language->id, $obj->id);
				if ($images)
				{
					echo '
					<tr>
						<td class="col-left"></td>
						<td style="padding-bottom:5px;">
							<div style="display:block;width:620px;" class="hint clear">
								'.$this->l('Do you want an image associated with the product in your description?').'
								<span class="addImageDescription" style="cursor:pointer">'.$this->l('Click here').'</span>.
								<table id="createImageDescription" style="display:none;">
									<tr>
										<td colspan="2" height="10"></td>
									</tr>
									<tr>
										<td class="col-left">'.$this->l('Select your image:').'</td>
										<td style="padding-bottom:5px;">
											<ul>';
											foreach ($images as $key => $image)
											{
												$checked = ($key == 0) ? 'checked' : '';
												echo '
													<li>
														<input type="radio" name="smallImage" id="smallImage_'.$key.'" value="'.$image['id_image'].'" '.$checked.'>';
												$urlImage = $this->context->link->getImageLink($obj->link_rewrite[$this->context->language->id], $obj->id.'-'.$image['id_image'], 'small');
												echo '
														<label for="smallImage_'.$key.'" class="t"><img src="'.$urlImage.'" alt="'.$image['legend'].'" /></label>
													</li>';
											}
										echo '
											</ul>
											<p class="clear"></p>
										</td>
									</tr>';
								echo '
									<tr>
										<td class="col-left">'.$this->l('Where to place it?').'</td>
										<td style="padding-bottom:5px;">
											<input type="radio" name="leftRight" id="leftRight_1" value="left" checked>
											<label for="leftRight_1" class="t">'.$this->l('left').'</label>
											<br />
											<input type="radio" name="leftRight" id="leftRight_2" value="right">
											<label for="leftRight_2" class="t">'.$this->l('right').'</label>
											<p class="clear"></p>
										</td>
									</tr>';
								echo '
									<tr>
										<td class="col-left">'.$this->l('Select the type of picture:').'</td>
										<td style="padding-bottom:5px;">';
											$imageTypes = ImageType::getImagesTypes('products');
											foreach ($imageTypes as $key => $type)
											{
												$checked = ($key == 0) ? 'checked' : '';
												echo '
													<input type="radio" name="imageTypes" id="imageTypes_'.$key.'" value="'.$type['name'].'" '.$checked.'>
													<label for="imageTypes_'.$key.'" class="t">'.$type['name'].' <span>('.$type['width'].'px par '.$type['height'].'px)</span></label>
													<br />';
											}
									echo '
											<p class="clear"></p>
										</td>
									</tr>';
								echo '
									<tr>
										<td class="col-left">'.$this->l('Image tag to insert:').'</td>
										<td style="padding-bottom:5px;">
											<input type="text" id="resultImage" name="resultImage" />
											<p>'.$this->l('The tag is to copy / paste in the description.').'</p>
										</td>
									</tr>
								</table>
							</div>
							<p class="clear"></p>
						</td>
					</tr>';
					echo '
					<script type="text/javascript">
						$(function() {
							changeTagImage();
							$("#createImageDescription input").change(function(){
								changeTagImage();
							});

							var i = 0;
							$(".addImageDescription").click(function(){
								if (i == 0){
									$("#createImageDescription").animate({opacity: 1, height: "toggle"}, 500);
									i = 1;
								}else{
									$("#createImageDescription").animate({opacity: 0, height: "toggle"}, 500);
									i = 0;
								}
							});
						});

						function changeTagImage(){
							var smallImage = $("input[name=smallImage]:checked").attr("value");
							var leftRight = $("input[name=leftRight]:checked").attr("value");
							var imageTypes = $("input[name=imageTypes]:checked").attr("value");
							$("#resultImage").val("{img-"+smallImage+"-"+leftRight+"-"+imageTypes+"}");
						}
					</script>';
				}
				echo '
					<tr>
						<td class="col-left">'.$this->l('Tags:').'</td>
						<td style="padding-bottom:5px;" class="translatable">';
				if ($obj->id)
					$obj->tags = Tag::getProductTags((int)$obj->id);
				foreach ($this->_languages as $language)
				{
					echo '<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
							<input size="55" type="text" id="tags_'.$language['id_lang'].'" name="tags_'.$language['id_lang'].'"
							value="'.htmlentities(Tools::getValue('tags_'.$language['id_lang'], $obj->getTags($language['id_lang'], true)), ENT_COMPAT, 'UTF-8').'" />
							<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' !<>;?=+#"&deg;{}_$%<span class="hint-pointer">&nbsp;</span></span>
						  </div>';
				}
				echo '	<p class="clear">'.$this->l('Tags separated by commas (e.g., dvd, dvd player, hifi)').'</p>
						</td>
					</tr>';
				$accessories = Product::getAccessoriesLight($this->context->language->id, $obj->id);

				if ($postAccessories = Tools::getValue('inputAccessories'))
				{
					$postAccessoriesTab = explode('-', Tools::getValue('inputAccessories'));
					foreach ($postAccessoriesTab as $accessoryId)
						if (!$this->haveThisAccessory($accessoryId, $accessories) && $accessory = Product::getAccessoryById($accessoryId))
							$accessories[] = $accessory;
				}

					echo '
					<tr>
						<td class="col-left">'.$this->l('Accessories:').'<br /><br /><i>'.$this->l('(Do not forget to Save the product afterward)').'</i></td>
						<td style="padding-bottom:5px;">
							<div id="divAccessories">';
					foreach ($accessories as $accessory)
						echo $accessory['name'].(!empty($accessory['reference']) ? ' ('.$accessory['reference'].')' : '').' <span onclick="delAccessory('.$accessory['id_product'].');" style="cursor: pointer;"><img src="../img/admin/delete.gif" class="middle" alt="" /></span><br />';
					echo '</div>
							<input type="hidden" name="inputAccessories" id="inputAccessories" value="';
					foreach ($accessories as $accessory)
						echo $accessory['id_product'].'-';
					echo '" />
							<input type="hidden" name="nameAccessories" id="nameAccessories" value="';
					foreach ($accessories as $accessory)
						echo $accessory['name'].'¤';

					echo '" />
							<script type="text/javascript">
								var formProduct;
								var accessories = new Array();
							</script>

							<link rel="stylesheet" type="text/css" href="'.__PS_BASE_URI__.'css/jquery.autocomplete.css" />
							<script type="text/javascript" src="'._PS_JS_DIR_.'jquery/jquery.autocomplete.js"></script>
							<div id="ajax_choose_product" style="padding:6px; padding-top:2px; width:600px;">
								<p class="clear">'.$this->l('Begin typing the first letters of the product name, then select the product from the drop-down list:').'</p>
								<input type="text" value="" id="product_autocomplete_input" />
								<img onclick="$(this).prev().search();" style="cursor: pointer;" src="../img/admin/add.gif" alt="'.$this->l('Add an accessory').'" title="'.$this->l('Add an accessory').'" />
							</div>
							<script type="text/javascript">
								urlToCall = null;
								/* function autocomplete */
								$(function() {
									$(\'#product_autocomplete_input\')
										.autocomplete(\'ajax_products_list.php\', {
											minChars: 1,
											autoFill: true,
											max:20,
											matchContains: true,
											mustMatch:true,
											scroll:false,
											cacheLength:0,
											formatItem: function(item) {
												return item[1]+\' - \'+item[0];
											}
										}).result(addAccessory);
									$(\'#product_autocomplete_input\').setOptions({
										extraParams: {excludeIds : getAccessorieIds()}
									});
								});
							</script>
						</td>
					</tr>
					<tr><td colspan="2" style="padding-bottom:10px;"><hr style="width:100%;" /></td></tr>
					<tr>
						<td colspan="2" style="text-align:center;">
							<input type="submit" value="'.$this->l('Save').'" name="submitAdd'.$this->table.'" class="button" />
							&nbsp;<input type="submit" value="'.$this->l('Save and stay').'" name="submitAdd'.$this->table.'AndStay" class="button" /></td>
					</tr>
				</table>
			<br />
			</div>';
			// TinyMCE
		$iso = $this->context->language->iso_code;
		$isoTinyMCE = (file_exists(_PS_JS_DIR_.'tiny_mce/langs/'.$iso.'.js') ? $iso : 'en');
		$ad = dirname($_SERVER["PHP_SELF"]);
		echo '
			<script type="text/javascript">
			var iso = \''.$isoTinyMCE.'\' ;
			var pathCSS = \''._THEME_CSS_DIR_.'\' ;
			var ad = \''.$ad.'\' ;
			</script>
			<script type="text/javascript" src="'._PS_JS_DIR_.'tiny_mce/tiny_mce.js"></script>
			<script type="text/javascript" src="'._PS_JS_DIR_.'tinymce.inc.js"></script>
			<script type="text/javascript">
					toggleVirtualProduct(getE(\'is_virtual_good\'));
					unitPriceWithTax(\'unit\');
			</script>';
		$categoryBox = Tools::getValue('categoryBox', array());

	}

	function displayFormImages($obj, $token = null)
	{
		if (!Tools::getValue('id_product'))
			return '';
		global $attributeJs, $images;
		$shops = false;
		if (Shop::isMultiShopActivated())
			$shops = Shop::getShops();

		$countImages = (int)Db::getInstance()->getValue('SELECT COUNT(id_product) FROM '._DB_PREFIX_.'image WHERE id_product = '.(int)$obj->id);
		$images = Image::getImages($this->context->language->id, $obj->id);
		$imagesTotal = Image::getImagesTotal($obj->id);

		echo '
		<div class="tab-page" id="step2">
				<h4 class="tab" >2. '.$this->l('Images').' (<span id="countImage">'.$countImages.'</span>)</h4>
				<table cellpadding="5">
				<tr>
					<td><b>'.(Tools::getValue('id_image')?$this->l('Edit this product image'):$this->l('Add a new image to this product')).'</b></td>
				</tr>
				</table>
				<hr style="width: 100%;" /><br />
				<table cellpadding="5" style="width:100%">
					<tr>
						<td class="col-left">'.$this->l('File:').'</td>
						<td style="padding-bottom:5px;">
						<div id="file-uploader">
							<noscript>
								<p>'.$this->l('Please enable JavaScript to use file uploader:').'.</p>
							</noscript>
						</div>
						<div id="progressBarImage" class="progressBarImage"></div>
						<div id="showCounter" style="display:none;"><span id="imageUpload">0</span><span id="imageTotal">0</span></div>
						<ul	id="listImage"></ul>
						<link href="../css/fileuploader.css" rel="stylesheet" type="text/css">
						<script type="text/javascript">var upbutton = "'.$this->l('Upload a file').'"; </script>
						<script src="'._PS_JS_DIR_.'fileuploader.js" type="text/javascript"></script>
						<script src="'._PS_JS_DIR_.'jquery/ui/jquery.ui.core.min.js" type="text/javascript"></script>
						<script src="'._PS_JS_DIR_.'jquery/ui/jquery.ui.widget.min.js" type="text/javascript"></script>
						<script src="'._PS_JS_DIR_.'jquery/ui/jquery.ui.progressbar.min.js" type="text/javascript"></script>
						<script type="text/javascript" src="'._PS_JS_DIR_.'admin.js"></script>
						<script type="text/javascript">
							function deleteImg(id)
							{
								var conf = confirm(\''.addslashes($this->l('Are you sure?', __CLASS__, true, false)).'\');
								if (conf)
									$.post("ajax-tab.php",
									{
										deleteImage: 1,
										id_image:id,
										id_product : "'.(int)Tools::getValue('id_product').'",
										id_category : "'.(int)$this->_category->id.'",
										token : "'.Tools::getAdminTokenLite('AdminCatalog').'",
										tab : "AdminCatalog",
										ajaxMode : 1,
										updateproduct : 1},
										function (data) {
											if (data)
											{
												cover = 0;
												if ($("#tr_" + id).find(".covered").attr("src") == "../img/admin/enabled.gif")
													cover = 1;
												$("#tr_" + id).remove();

												if (cover)
													$("#imageTable tr").eq(1).find(".covered").attr("src", "../img/admin/enabled.gif");

												$("#countImage").html(parseInt($("#countImage").html()) - 1);

												refreshImagePositions($("#imageTable"));
											}
									});
							}
							function delQueue(id)
							{
								$("#img" + id).fadeOut("slow");
								$("#img" + id).remove();
							}
							$(document).ready(function () {
								var filecheck = 1;
								var uploader = new qq.FileUploader({
									element: document.getElementById("file-uploader"),
									action: \'ajax-tab.php\',
									debug: false,
									onComplete: function(id, fileName, responseJSON){
										var percent = ((filecheck * 100) / nbfile);
										$("#progressBarImage").progressbar({value: percent });
										if (percent != 100)
										{
											$("#imageUpload").html(parseInt(filecheck));
											$("#imageTotal").html(" / " + parseInt(nbfile) + " '.$this->l('Images').'");
											$("#progressBarImage").show();
											$("#showCounter").show();
										}
										else
										{
											$("#progressBarImage").progressbar({value: 0 });
											$("#progressBarImage").hide();
											$("#showCounter").hide();
											nbfile = 0;
											filecheck = 0;
										}
										if (responseJSON.success)
										{
											$("#imageTable tr:last").after(responseJSON.success);
											$("#countImage").html(parseInt($("#countImage").html()) + 1);
											$("#img" + id).remove();
										}
										else
										{
											$("#img" + id).addClass("red");
											$("#img" + id + " .errorImg").html(responseJSON.error);
											$("#img" + id + " .errorImg").show();

										}
										if (percent >= 100)
										{
											refreshImagePositions($("#imageTable"));
										}
										filecheck++;
									},
									onSubmit: function(id, filename){
										$("#imageTable").show();
										$("#listImage").append("<li id=\'img"+id+"\'><div class=\"float\" >" + filename + "</div></div><a style=\"margin-left:10px;\" href=\"javascript:delQueue(" + id +");\"><img src=\"../img/admin/disabled.gif\" alt=\"\" border=\"0\"></a><p class=\"errorImg\"></p></li>");
									},
									params: {
										id_product : "'.(int)Tools::getValue('id_product').'",
										id_category : "'.(int)$this->_category->id.'",
										token : "'.Tools::getAdminTokenLite('AdminCatalog').'",
										tab : "AdminCatalog",
										updateproduct : 1,
										addImage : 1,
										ajaxMode : 1,
										},

								});
							});
        			  </script>
							<p class="float" style="clear: both;">
								'.$this->l('Format:').' JPG, GIF, PNG. '.$this->l('Filesize:').' '.($this->maxImageSize / 1000).''.$this->l('Kb max.').'
							</p>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:center;">
						<input type="hidden" name="resizer" value="auto" />
							'.(Tools::getValue('id_image') ? '<input type="hidden" name="id_image" value="'.(int)(Tools::getValue('id_image')).'" />' : '').'
						</td>
					</tr>
					<tr><td colspan="2" style="padding-bottom:10px;"><hr style="width:100%;" /></td></tr>
					<tr>
						<td colspan="2">
							<script type="text/javascript" src="'._PS_JS_DIR_.'jquery/jquery.tablednd_0_5.js"></script>
							<script type="text/javascript">
								var token = \''.($token != null ? $token : $this->token).'\';
								var come_from = \''.$this->table.'\';
								var alternate = \''.($this->_orderWay == 'DESC' ? '1' : '0' ).'\';
							</script>
							<script type="text/javascript" src="'._PS_JS_DIR_.'admin-dnd.js"></script>
							<table cellspacing="0" cellpadding="0" class="table tableDnD" id="imageTable" style="display:'.($countImages == 0 ? 'none' : '').';">
								<thead>
								<tr>
									<th style="width: 100px;">'.$this->l('Image').'</th>
									<th>&nbsp;</th>
									<th>'.$this->l('Position').'</th>';
						if ($shops)
						{
							echo '<script type="text/javascript">
											$(document).ready(function() {
												$(\'.image_shop\').change(function() {
													$.post("ajax-tab.php",
														{
															updateProductImageShopAsso: 1,
															id_image:$(this).attr("name"),
															id_shop: $(this).val(),
															active:$(this).attr("checked"),
															id_product : "'.(int)Tools::getValue('id_product').'",
															id_category : "'.(int)$this->_category->id.'",
															token : "'.Tools::getAdminTokenLite('AdminCatalog').'",
															tab : "AdminCatalog",
															updateproduct : 1,
														});
												});
											});
										</script>';
							foreach ($shops as $shop)
								echo '<th>'.$shop['name'].'</th>';
						}
						echo '
									<th>'.$this->l('Cover').'</th>
									<th>'.$this->l('Action').'</th>
								</tr></thead>';

						foreach ($images as $k => $image)
							echo $this->getLineTableImage($image, $imagesTotal, $token, $shops);
			echo '
							</table>
						</td>
					</tr>
				</table>
			</div>';
			echo '
			<script type="text/javascript" src="'._PS_JS_DIR_.'attributesBack.js"></script>
			<script type="text/javascript">
				var attrs = new Array();
				var modifyattributegroup = \''.addslashes(html_entity_decode($this->l('Modify this attribute combination'), ENT_COMPAT, 'UTF-8')).'\';
				attrs[0] = new Array(0, \'---\');';

			$attributes = Attribute::getAttributes($this->context->language->id, true);
			$attributeJs = array();

			foreach ($attributes as $k => $attribute)
				$attributeJs[$attribute['id_attribute_group']][$attribute['id_attribute']] = $attribute['name'];

			foreach ($attributeJs as $idgrp => $group)
			{
				echo '
				attrs['.$idgrp.'] = new Array(0, \'---\' ';
				foreach ($group as $idattr => $attrname)
					echo ', '.$idattr.', \''.addslashes(($attrname)).'\'';
				echo ');';
			}
			echo '
			</script>';
	}

	public function getLineTableImage($image, $imagesTotal, $token, $shops)
	{
		if (Shop::isMultiShopActivated())
			$imgObj = new Image((int)$image['id_image']);
		$image_obj = new Image($image['id_image']);
		$img_path = $image_obj->getExistingImgPath();
		$html =  '
			<tr id="tr_'.$image_obj->id.'">
				<td style="padding: 4px;"><a href="'._THEME_PROD_DIR_.$img_path.'.jpg" target="_blank">
					<img src="'._THEME_PROD_DIR_.$img_path.'-small.jpg'.((int)(Tools::getValue('image_updated')) === (int)($image['id_image']) ? '?date='.time() : '').'"
					alt="'.htmlentities(stripslashes($image['legend']), ENT_COMPAT, 'UTF-8').'" title="'.htmlentities(stripslashes($image['legend']), ENT_COMPAT, 'UTF-8').'" /></a>
				</td>
				<td class="center positionImage">'.(int)($image['position']).'</td>
				<td id="td_'.$image_obj->id.'" class="pointer dragHandle center">';

		$html .= '
				<a '.($image['position'] == 1 ? ' style="display: none;"' : '').' href="'.self::$currentIndex.'&id_image='.$image['id_image'].'&imgPosition='.($image['position'] - 1).'&imgDirection=0&token='.($token ? $token : $this->token).'"><img src="../img/admin/up.gif" alt="" border="0"></a>
				<a '.($image['position'] == $imagesTotal ? ' style="display: none;"' : '').' href="'.self::$currentIndex.'&id_image='.$image['id_image'].'&imgPosition='.($image['position'] + 1).'&imgDirection=1&token='.($token ? $token : $this->token).'"><img src="../img/admin/down.gif" alt="" border="0"></a>';
		$html .= '
				</td>';
		if (Shop::isMultiShopActivated())
			foreach ($shops as $shop)
				$html .= '
				<td class="center"><input type="checkbox" class="image_shop" name="'.(int)$image['id_image'].'" value="'.(int)$shop['id_shop'].'" '.($imgObj->isAssociatedToShop($shop['id_shop']) ? 'checked="1"' : '').' /></td>';
		$html .= '
				<td class="center"><a href="'.self::$currentIndex.'&id_image='.$image['id_image'].'&coverImage&token='.($token ? $token : $this->token).'"><img class="covered" src="../img/admin/'.($image['cover'] ? 'enabled.gif' : 'forbbiden.gif').'" alt="" /></a></td>
				<td class="center">
				<a href="#" onclick="deleteImg('.(int)$image['id_image'].');"><img src="../img/admin/delete.gif" alt="'.$this->l('Delete this image').'" title="'.$this->l('Delete this image').'" /></a>
				</td>
			</tr>';
		return $html;
	}

	public function initCombinationImagesJS()
	{
		if (!($obj = $this->loadObject(true)))
			return;

		$content = 'var combination_images = new Array();';
		if (!$allCombinationImages = $obj->getCombinationImages($this->context->language->id))
			return $content;
		foreach ($allCombinationImages as $id_product_attribute => $combinationImages)
		{
			$i = 0;
			$content .= 'combination_images['.(int)($id_product_attribute).'] = new Array();';
			foreach ($combinationImages as $combinationImage)
				$content .= 'combination_images['.(int)($id_product_attribute).']['.$i++.'] = '.(int)($combinationImage['id_image']).';';
		}
		return $content;
	}

	public function displayFormAttributes($obj, $languages, $defaultLanguage)
	{
		if (!Combination::isFeatureActive())
		{
			$this->displayWarning($this->l('This feature has been disabled, you can active this feature at this page:').' <a href="index.php?tab=AdminPerformance&token='.Tools::getAdminTokenLite('AdminPerformance').'#featuresDetachables">'.$this->l('Performances').'</a>');
			return;
		}

		$attributeJs = array();
		$attributes = Attribute::getAttributes($this->context->language->id, true);
		foreach ($attributes as $k => $attribute)
			$attributeJs[$attribute['id_attribute_group']][$attribute['id_attribute']] = $attribute['name'];
		$currency = $this->context->currency;
		$attributes_groups = AttributeGroup::getAttributesGroups($this->context->language->id);
		$default_country = new Country((int)Configuration::get('PS_COUNTRY_DEFAULT'));
							
		$productDownload = new ProductDownload();
		$id_product_download = (int) $productDownload->getIdFromIdProduct($this->getFieldValue($obj, 'id'));
		if (!empty($id_product_download))
			$productDownload = new ProductDownload($id_product_download);
		
		$images = Image::getImages($this->context->language->id, $obj->id);
		if ($obj->id)
		{
			echo '
			<script type="text/javascript">
				$(document).ready(function(){
					$(\'#id_mvt_reason\').change(function(){
						updateMvtStatus($(this).val());
					});
					updateMvtStatus($(this).val());
					
					if ( $("input[name=is_virtual_file]:checked").val() == 1)
					{
						$("#virtual_good_attributes").show();
						$("#is_virtual_file_product").show();
					}
					else
					{
						$("#virtual_good_attributes").hide();
						$("#is_virtual_file_product").hide();
					}
					
					$("input[name=is_virtual_file]").live("change", function() {
						if($(this).val() == "1")
						{
							$("#virtual_good_attributes").show();
							$("#is_virtual_file_product").show();
						}
						else
						{
							$("#virtual_good_attributes").hide();
							$("#is_virtual_file_product").hide();
						}
					});
				}); 
			</script>
			<table cellpadding="5">
				<tr>
					<td colspan="2"><b>'.$this->l('Add or modify combinations for this product').'</b> -
					&nbsp;<a href="index.php?tab=AdminCatalog&id_product='.$obj->id.'&id_category='.(int)(Tools::getValue('id_category')).'&attributegenerator&token='.Tools::getAdminToken('AdminCatalog'.(int)(Tab::getIdFromClassName('AdminCatalog')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('Are you sure you want to delete entered product information?', __CLASS__, true, false).'\');"><img src="../img/admin/appearance.gif" alt="combinations_generator" class="middle" title="'.$this->l('Product combinations generator').'" />&nbsp;'.$this->l('Product combinations generator').'</a>
					</td>
				</tr>
			</table>
			<hr style="width:100%;" /><br />
			<table cellpadding="5" style="width:100%">
			<tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" valign="top">'.$this->l('Group:').'</td>
			  <td style="padding-bottom:5px;"><select name="attribute_group" id="attribute_group" style="width: 200px;" onchange="populate_attrs();">';
				if (isset($attributes_groups))
					foreach ($attributes_groups as $k => $attribute_group)
						if (isset($attributeJs[$attribute_group['id_attribute_group']]))
							echo '
							<option value="'.$attribute_group['id_attribute_group'].'">
							'.htmlentities(stripslashes($attribute_group['name']), ENT_COMPAT, 'UTF-8').'&nbsp;&nbsp;</option>';
				echo '
				</select></td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" valign="top">'.$this->l('Attribute:').'</td>
			  <td style="padding-bottom:5px;"><select name="attribute" id="attribute" style="width: 200px;">
			  <option value="0">---</option>
			  </select>
			  <script type="text/javascript" language="javascript">populate_attrs();</script>
			  </td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" valign="top">
			  <input style="width: 140px; margin-bottom: 10px;" type="button" value="'.$this->l('Add').'" class="button" onclick="add_attr();"/><br />
			  <input style="width: 140px;" type="button" value="'.$this->l('Delete').'" class="button" onclick="del_attr()"/></td>
			  <td align="left">
				  <select id="product_att_list" name="attribute_combinaison_list[]" multiple="multiple" size="4" style="width: 320px;"></select>
				</td>
		  </tr>
		  
		  <tr><td colspan="2"><hr style="width:100%;" /></td></tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Reference:').'</td>
			  <td style="padding-bottom:5px;">
				<input size="55" type="text" id="attribute_reference" name="attribute_reference" value="" style="width: 130px; margin-right: 44px;" />
				'.$this->l('EAN13:').'<input size="55" maxlength="13" type="text" id="attribute_ean13" name="attribute_ean13" value="" style="width: 110px; margin-left: 10px; margin-right: 44px;" />
				'.$this->l('UPC:').'<input size="55" maxlength="12" type="text" id="attribute_upc" name="attribute_upc" value="" style="width: 110px; margin-left: 10px;" />
				<span class="hint" name="help_box">'.$this->l('Special characters allowed:').' .-_#<span class="hint-pointer">&nbsp;</span></span>
			  </td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Supplier Reference:').'</td>
			  <td style="padding-bottom:5px;">
				<input size="55" type="text" id="attribute_supplier_reference" name="attribute_supplier_reference" value="" style="width: 130px; margin-right: 44px;" />
				'.$this->l('Location:').'<input size="55" type="text" id="attribute_location" name="attribute_location" value="" style="width: 101px; margin-left: 10px;" />
				<span class="hint" name="help_box">'.$this->l('Special characters allowed:').' .-_#<span class="hint-pointer">&nbsp;</span></span>
			  </td>
		  </tr>
		 <tr><td colspan="2"><hr style="width:100%;" /></td></tr>
	  <table cellpadding="5" id="virtual_good_attributes" style="width:100%;display:none;">
		<tr>
			<td colspan="2">	
				<div style="padding:5px;width:50%;float:left;margin-right:20px;border-right:1px solid #E0D0B1">
					<p>'.$this->l('Your server\'s maximum upload file size is') . ':&nbsp;' . ini_get('upload_max_filesize').'</p>
					<label id="virtual_product_file_attribute_label" for="virtual_product_file_attribute" class="t">'.$this->l('Upload a file').'</label>
					<p><input id="virtual_product_file_attribute" name="virtual_product_file_attribute" onchange="uploadFile2();" maxlength="'.$this->maxFileSize.'" type="file"></p>
					<div id="upload-confirmation2">';

					echo '<p id="gethtmlink" style="display: none;">'.$this->l('This is the link').':&nbsp;'.$productDownload->getHtmlLink(false, true).'
					<a id="make_downloadable_product_attribute" onclick="return confirm(\''.addslashes($this->l('Delete this file')).'\')" href="'.$_SERVER['HTTP_REFERER'].'&deleteVirtualProductAttribute=true'.'" class="red">'.$this->l('Delete this file').'</a></p>';
						
					echo '</div>
					<a id="delete_downloadable_product_attribute" style="display:none;" onclick="return confirm(\''.addslashes($this->l('Delete this file')).'\')" href="'.$_SERVER['HTTP_REFERER'].'&deleteVirtualProductAttribute=true" class="red">'.$this->l('Delete this file').'</a>';
					
					if ($up_filename = strval(Tools::getValue('virtual_product_filename_attribute')))
						echo '<input type="hidden" id="virtual_product_filename_attribute" name="virtual_product_filename_attribute" value="'.$up_filename.'" />';
	
					echo '<p class="block">
						<label for="virtual_product_name" class="t">'.$this->l('Filename').'</label>
						<input id="virtual_product_name_attribute" name="virtual_product_name_attribute" style="width:200px" value="" type="text">
						<span class="hint" name="help_box" style="display:none;">'.$this->l('The full filename with its extension (e.g., Book.pdf)').'</span>
					</p>
				</div>
				<div id="virtual_good_more_attribute" style="padding:5px;width:40%;float:left;margin-left:10px">
					<p class="block">
						<label for="virtual_product_nb_downloable" class="t">'.$this->l('Number of downloads').'</label>
						<input type="text" id="virtual_product_nb_downloable_attribute" name="virtual_product_nb_downloable_attribute" value="" class="" size="6" />
						<span class="hint" name="help_box" style="display:none">'.$this->l('Number of authorized downloads per customer').'</span>
					</p>
					<p class="block">
						<label for="virtual_product_expiration_date_attribute" class="t">'.$this->l('Expiration date').'</label>
						<input type="text" id="virtual_product_expiration_date_attribute" name="virtual_product_expiration_date_attribute" value="" size="11" maxlength="10" autocomplete="off" /> '.$this->l('Format: YYYY-MM-DD').'
						<span class="hint" name="help_box" style="display:none">'.$this->l('No expiration date if you leave this blank').'</span>
					</p>
					<p class="block">
						<label for="virtual_product_nb_days" class="t">'.$this->l('Number of days').'</label>
						<input type="text" id="virtual_product_nb_days_attribute" name="virtual_product_nb_days_attribute" value="" class="" size="4" /><sup> *</sup>
						<span class="hint" name="help_box" style="display:none">'.$this->l('How many days this file can be accessed by customers').' - <em>('.$this->l('set to zero for unlimited access').')</em></span>
					</p>
					<p class="block">
						<label for="virtual_product_is_shareable_attribute" class="t">'.$this->l('is shareable').'</label>
						<input type="checkbox" id="virtual_product_is_shareable_attribute" name="virtual_product_is_shareable" value="1" />
						<span class="hint" name="help_box" style="display:none">'.$this->l('Specify if the file can be shared').'</span>
					</p>
				</div>';
				includeDatepicker('virtual_product_expiration_date_attribute');
			echo '</td>
		</tr>
		<tr><td colspan="2"><hr style="width:100%;" /></td></tr>
		</table>
		 <table>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Wholesale price:').'</td>
			  <td style="padding-bottom:5px;">'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<input type="text" size="6"  name="attribute_wholesale_price" id="attribute_wholesale_price" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' ('.$this->l('overrides Wholesale price on Information tab').')</td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Impact on price:').'</td>
			  <td colspan="2" style="padding-bottom:5px;">
				<select name="attribute_price_impact" id="attribute_price_impact" style="width: 140px;" onchange="check_impact(); calcImpactPriceTI();">
				  <option value="0">'.$this->l('None').'</option>
				  <option value="1">'.$this->l('Increase').'</option>
				  <option value="-1">'.$this->l('Reduction').'</option>
				</select>
				<span id="span_impact">&nbsp;&nbsp;'.$this->l('of').'&nbsp;&nbsp;'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'
					<input type="text" size="6" name="attribute_price" id="attribute_price" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\'); calcImpactPriceTI();"/>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '');
					if ($this->context->country->display_tax_label)
					{
						echo ' '.$this->l('(tax excl.)').'<span '.(Tax::excludeTaxeOption() ? 'style="display:none"' : '' ).'> '.$this->l('or').' '.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'
							<input type="text" size="6" name="attribute_priceTI" id="attribute_priceTI" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\'); calcImpactPriceTE();"/>'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' '.$this->l('(tax incl.)').'</span> '.$this->l('final product price will be set to').' '.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<span id="attribute_new_total_price">0.00</span>'.($currency->format % 2 == 0 ? $currency->sign.' ' : '');
					}
			echo '
				</span>
			</td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Impact on weight:').'</td>
			  <td colspan="2" style="padding-bottom:5px;"><select name="attribute_weight_impact" id="attribute_weight_impact" style="width: 140px;" onchange="check_weight_impact();">
			  <option value="0">'.$this->l('None').'</option>
			  <option value="1">'.$this->l('Increase').'</option>
			  <option value="-1">'.$this->l('Reduction').'</option>
			  </select>
			  <span id="span_weight_impact">&nbsp;&nbsp;'.$this->l('of').'&nbsp;&nbsp;
				<input type="text" size="6" name="attribute_weight" id="attribute_weight" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" /> '.Configuration::get('PS_WEIGHT_UNIT').'</span></td>
		  </tr>
		  <tr id="tr_unit_impact">
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Impact on unit price :').'</td>
			  <td colspan="2" style="padding-bottom:5px;"><select name="attribute_unit_impact" id="attribute_unit_impact" style="width: 140px;" onchange="check_unit_impact();">
			  <option value="0">'.$this->l('None').'</option>
			  <option value="1">'.$this->l('Increase').'</option>
			  <option value="-1">'.$this->l('Reduction').'</option>
			  </select>
			  <span id="span_unit_impact">&nbsp;&nbsp;'.$this->l('of').'&nbsp;&nbsp;'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'
				<input type="text" size="6" name="attribute_unity" id="attribute_unity" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' / <span id="unity_third">'.$this->getFieldValue($obj, 'unity').'</span>
			</span></td>
		  </tr>';
		if (Configuration::get('PS_USE_ECOTAX'))
			echo'
				  <tr>
					  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;">'.$this->l('Eco-tax:').'</td>
					  <td style="padding-bottom:5px;">'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').'<input type="text" size="3" name="attribute_ecotax" id="attribute_ecotax" value="0.00" onKeyUp="if (isArrowKey(event)) return ;this.value = this.value.replace(/,/g, \'.\');" />'.($currency->format % 2 == 0 ? ' '.$currency->sign : '').' ('.$this->l('overrides Eco-tax on Information tab').')</td>
				  </tr>';

		echo'
		  <tr id="initial_stock_attribute">
				<td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" class="col-left">'.$this->l('Initial stock:').'</td>
				<td><input type="text" name="attribute_quantity" size="3" maxlength="6" value="0"/></td>
		  </tr>
		  </tr>
			<tr id="stock_mvt_attribute" style="display:none;">
				<td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" class="col-left">'.$this->l('Stock movement:').'</td>
				<td style="padding-bottom:5px;">
					<select id="id_mvt_reason" name="id_mvt_reason">
						<option value="-1">--</option>';
			$reasons = StockMvtReason::getStockMvtReasons($this->context->language->id);
			foreach ($reasons as $reason)
				echo '<option rel="'.$reason['sign'].'" value="'.$reason['id_stock_mvt_reason'].'" '.(Configuration::get('PS_STOCK_MVT_REASON_DEFAULT') == $reason['id_stock_mvt_reason'] ? 'selected="selected"' : '').'>'.$reason['name'].'</option>';
			echo '</select>
					<input type="text" name="attribute_mvt_quantity" size="3" maxlength="6" value="0"/>&nbsp;&nbsp;
					<span style="display:none;" id="mvt_sign"></span>
					<br />
					<div class="hint clear" style="display: block;width: 70%;">'.$this->l('Choose the reason and enter the quantity that you want to increase or decrease in your stock').'</div>
				</td>
			</tr>
			<tr>
			<td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" class="col-left">'.$this->l('Minimum quantity:').'</td>
				<td style="padding-bottom:5px;">
					<input size="3" maxlength="6" name="minimal_quantity" id="minimal_quantity" type="text" value="'.($this->getFieldValue($obj, 'minimal_quantity') ? $this->getFieldValue($obj, 'minimal_quantity') : 1).'" />
					<p>'.$this->l('The minimum quantity to buy this product (set to 1 to disable this feature)').'</p>
				</td>
			</tr>
		  <tr style="display:none;" id="attr_qty_stock">
			  <td style="width:150px">'.$this->l('Quantity in stock:').'</td>
			  <td style="padding-bottom:5px;"><b><span style="display:none;" id="attribute_quantity"></span></b></td>
		  </tr>
		  <tr>
			  <td style="width:150px;vertical-align:top;text-align:right;padding-right:10px;font-weight:bold;" class="col-left" style="width:150px">'.$this->l('Available date:').'</td>
			  <td style="padding-bottom:5px;"><input id="available_date" name="available_date" value="'.(($this->getFieldValue($obj, 'available_date') != 0) ? stripslashes(htmlentities(Tools::displayDate($this->getFieldValue($obj, 'available_date'), $language['id_lang']))) : '0000-00-00').'" style="text-align: center;" type="text" />
				  <p>'.$this->l('The available date when this product is out of stock').'</p>
			  </td>
		  </tr>';
		  // date picker include
		  includeDatepicker('available_date');
		echo '
		  <tr><td colspan="2"><hr style="width:100%;" /></td></tr>
		  <tr>
			  <td style="width:150px">'.$this->l('Image:').'</td>
			  <td style="padding-bottom:5px;">
				<ul id="id_image_attr">';
			$i = 0;
			$imageType = ImageType::getByNameNType('small', 'products');
			$imageWidth = (isset($imageType['width']) ? (int)($imageType['width']) : 64) + 25;
			foreach ($images as $image)
			{
				$imageObj = new Image($image['id_image']);
				echo '<li style="float: left; width: '.$imageWidth.'px;"><input type="checkbox" name="id_image_attr[]" value="'.(int)($image['id_image']).'" id="id_image_attr_'.(int)($image['id_image']).'" />
				<label for="id_image_attr_'.(int)($image['id_image']).'" style="float: none;"><img src="'._THEME_PROD_DIR_.$imageObj->getExistingImgPath().'-small.jpg" alt="'.htmlentities(stripslashes($image['legend']), ENT_COMPAT, 'UTF-8').'" title="'.htmlentities(stripslashes($image['legend']), ENT_COMPAT, 'UTF-8').'" /></label></li>';
				++$i;
			}
			echo '</ul>
				<img id="pic" alt="" title="" style="display: none; width: 100px; height: 100px; float: left; border: 1px dashed #BBB; margin-left: 20px;" />
			  </td>
		  </tr>
			<tr>
			  <td style="width:150px">'.$this->l('Default:').'<br /><br /></td>
			  <td style="padding-bottom:5px;">
				<input type="checkbox" name="attribute_default" id="attribute_default" value="1" />&nbsp;'.$this->l('Make this the default combination for this product').'<br /><br />
			  </td>
		  </tr>
		  <tr>
			  <td style="width:150px">&nbsp;</td>
			  <td style="padding-bottom:5px;">
				<span style="float: left;"><input type="submit" name="submitProductAttribute" id="submitProductAttribute" value="'.$this->l('Add this combination').'" class="button" onclick="attr_selectall(); this.form.action += \'&addproduct&tabs=3\';" /> </span>
				<span id="ResetSpan" style="float: left; margin-left: 8px; display: none;">
				  <input type="reset" name="ResetBtn" id="ResetBtn" onclick="init_elems(); getE(\'submitProductAttribute\').value = \''.$this->l('Add this attributes group', __CLASS__, true).'\';
				  getE(\'id_product_attribute\').value = 0; $(\'#ResetSpan\').slideToggle();" class="button" value="'.$this->l('Cancel modification').'" /></span><span class="clear"></span>
			  </td>
		  </tr>
		  <tr><td colspan="2"><hr style="width:100%;" /></td></tr>
		  <tr>
			  <td colspan="2">
					<br />
					<table border="0" cellpadding="0" cellspacing="0" class="table">
						<tr>
							<th>'.$this->l('Attributes').'</th>
							<th>'.$this->l('Impact').'</th>
							<th>'.$this->l('Weight').'</th>
							<th>'.$this->l('Reference').'</th>
							<th>'.$this->l('EAN13').'</th>
							<th>'.$this->l('UPC').'</th>
							<th class="center">'.$this->l('Quantity').'</th>';

							if ($id_product_download && !empty($productDownload->display_filename))
							{
								echo '
								<th class="center">'.$this->l('Filename').'</th>
								<th class="center">'.$this->l('Number of downloads').'</th>
								<th class="center">'.$this->l('Number of days').'</th>
								<th class="center">'.$this->l('Share').'</th>';
							}
							
							echo '<th class="center">'.$this->l('Actions').'</th>
						</tr>';
			if ($obj->id)
			{
				/* Build attributes combinaisons */
				$combinaisons = $obj->getAttributeCombinaisons($this->context->language->id);
				$groups = array();
				if (is_array($combinaisons))
				{
					$combinationImages = $obj->getCombinationImages($this->context->language->id);
					foreach ($combinaisons as $k => $combinaison)
					{
						$combArray[$combinaison['id_product_attribute']]['wholesale_price'] = $combinaison['wholesale_price'];
						$combArray[$combinaison['id_product_attribute']]['price'] = $combinaison['price'];
						$combArray[$combinaison['id_product_attribute']]['weight'] = $combinaison['weight'];
						$combArray[$combinaison['id_product_attribute']]['unit_impact'] = $combinaison['unit_price_impact'];
						$combArray[$combinaison['id_product_attribute']]['reference'] = $combinaison['reference'];
                        $combArray[$combinaison['id_product_attribute']]['supplier_reference'] = $combinaison['supplier_reference'];
                        $combArray[$combinaison['id_product_attribute']]['ean13'] = $combinaison['ean13'];
						$combArray[$combinaison['id_product_attribute']]['upc'] = $combinaison['upc'];
						$combArray[$combinaison['id_product_attribute']]['minimal_quantity'] = $combinaison['minimal_quantity'];
						$combArray[$combinaison['id_product_attribute']]['available_date'] = strftime($combinaison['available_date']);
						$combArray[$combinaison['id_product_attribute']]['location'] = $combinaison['location'];
						$combArray[$combinaison['id_product_attribute']]['quantity'] = $combinaison['quantity'];
						$combArray[$combinaison['id_product_attribute']]['id_image'] = isset($combinationImages[$combinaison['id_product_attribute']][0]['id_image']) ? $combinationImages[$combinaison['id_product_attribute']][0]['id_image'] : 0;
						$combArray[$combinaison['id_product_attribute']]['default_on'] = $combinaison['default_on'];
						$combArray[$combinaison['id_product_attribute']]['ecotax'] = $combinaison['ecotax'];
						$combArray[$combinaison['id_product_attribute']]['attributes'][] = array($combinaison['group_name'], $combinaison['attribute_name'], $combinaison['id_attribute']);
						if ($combinaison['is_color_group'])
							$groups[$combinaison['id_attribute_group']] = $combinaison['group_name'];
					}
				}
				$irow = 0;
				if (isset($combArray))
				{
					foreach ($combArray as $id_product_attribute => $product_attribute)
					{
						$list = '';
						$jsList = '';

						/* In order to keep the same attributes order */
						asort($product_attribute['attributes']);

						foreach ($product_attribute['attributes'] as $attribute)
						{
							$list .= addslashes(htmlspecialchars($attribute[0])).' - '.addslashes(htmlspecialchars($attribute[1])).', ';
							$jsList .= '\''.addslashes(htmlspecialchars($attribute[0])).' : '.addslashes(htmlspecialchars($attribute[1])).'\', \''.$attribute[2].'\', ';
						}
						$list = rtrim($list, ', ');
						$jsList = rtrim($jsList, ', ');
						$attrImage = $product_attribute['id_image'] ? new Image($product_attribute['id_image']) : false;
						$available_date = ($product_attribute['available_date'] != 0) ? date('Y-m-d', strtotime($product_attribute['available_date'])) : '0000-00-00';

						$id_product_download = $productDownload->getIdFromIdAttibute((int) $id_product_attribute);
								
						if ($id_product_download)
							$productDownload = new ProductDownload($id_product_download);
						
						$available_date_attribute = substr($productDownload->date_expiration, 0, -9);
						
						if ($available_date_attribute == '0000-00-00')
							$available_date_attribute = '';	
							
						if ($productDownload->is_shareable == 1)
							$is_shareable = $this->l('Yes');
						else
							$is_shareable = $this->l('No');

						echo '
						<tr'.($irow++ % 2 ? ' class="alt_row"' : '').($product_attribute['default_on'] ? ' style="background-color:#D1EAEF"' : '').'>
							<td>'.stripslashes($list).'</td>
							<td class="right">'.($currency->format % 2 != 0 ? $currency->sign.' ' : '').$product_attribute['price'].($currency->format % 2 == 0 ? ' '.$currency->sign : '').'</td>
							<td class="right">'.$product_attribute['weight'].Configuration::get('PS_WEIGHT_UNIT').'</td>
							<td class="right">'.$product_attribute['reference'].'</td>
							<td class="right">'.$product_attribute['ean13'].'</td>
							<td class="right">'.$product_attribute['upc'].'</td>
							<td class="center">'.$product_attribute['quantity'].'</td>';
							
							if ($id_product_download && !empty($productDownload->display_filename))
							{
								echo '<td class="right">'.$productDownload->getHtmlLink(false, true).'</td>
								<td class="center">'.$productDownload->nb_downloadable.'</td>
								<td class="center">'.$productDownload->nb_downloadable.'</td>
								<td class="right">'.$is_shareable.'</td>';
							}
							
							$exists_file = realpath(_PS_DOWNLOAD_DIR_).'/'.$productDownload->filename;
							
							if ($productDownload->id && file_exists($exists_file))
								$filename = $productDownload->filename;
							else
								$filename = '';
							
							echo '<td class="center">
							<a style="cursor: pointer;">
							<img src="../img/admin/edit.gif" alt="'.$this->l('Modify this combination').'"
							onclick="javascript:fillCombinaison(\''.$product_attribute['wholesale_price'].'\', \''.$product_attribute['price'].'\', \''.$product_attribute['weight'].'\', \''.$product_attribute['unit_impact'].'\', \''.$product_attribute['reference'].'\', \''.$product_attribute['supplier_reference'].'\', \''.$product_attribute['ean13'].'\',
							\''.$product_attribute['quantity'].'\', \''.($attrImage ? $attrImage->id : 0).'\', Array('.$jsList.'), \''.$id_product_attribute.'\', \''.$product_attribute['default_on'].'\', \''.$product_attribute['ecotax'].'\', \''.$product_attribute['location'].'\', \''.$product_attribute['upc'].'\', \''.$product_attribute['minimal_quantity'].'\', \''.$available_date.'\', 
							\''.$productDownload->display_filename.'\', \''.$filename.'\', \''.$productDownload->nb_downloadable.'\', \''.$available_date_attribute.'\',  \''.$productDownload->nb_days_accessible.'\',  \''.$productDownload->is_shareable.'\'); calcImpactPriceTI();" /></a>&nbsp;
							'.(!$product_attribute['default_on'] ? '<a href="'.self::$currentIndex.'&defaultProductAttribute&id_product_attribute='.$id_product_attribute.'&id_product='.$obj->id.'&'.(Tools::isSubmit('id_category') ? 'id_category='.(int)(Tools::getValue('id_category')).'&' : '&').'token='.Tools::getAdminToken('AdminCatalog'.(int)(Tab::getIdFromClassName('AdminCatalog')).$this->context->employee->id).'">
							<img src="../img/admin/asterisk.gif" alt="'.$this->l('Make this the default combination').'" title="'.$this->l('Make this combination the default one').'"></a>' : '').'
							<a href="'.self::$currentIndex.'&deleteProductAttribute&id_product_attribute='.$id_product_attribute.'&id_product='.$obj->id.'&'.(Tools::isSubmit('id_category') ? 'id_category='.(int)(Tools::getValue('id_category')).'&' : '&').'token='.Tools::getAdminToken('AdminCatalog'.(int)(Tab::getIdFromClassName('AdminCatalog')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('Are you sure?', __CLASS__, true, false).'\');">
							<img src="../img/admin/delete.gif" alt="'.$this->l('Delete this combination').'" /></a></td>
						</tr>';
					}
					echo '<tr><td colspan="7" align="center"><a href="'.self::$currentIndex.'&deleteAllProductAttributes&id_product='.$obj->id.'&token='.Tools::getAdminToken('AdminCatalog'.(int)(Tab::getIdFromClassName('AdminCatalog')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('Are you sure?', __CLASS__, true, false).'\');"><img src="../img/admin/delete.gif" alt="'.$this->l('Delete this combination').'" /> '.$this->l('Delete all combinations').'</a></td></tr>';
				}
				else
					echo '<tr><td colspan="7" align="center"><i>'.$this->l('No combination yet').'.</i></td></tr>';
			}
			echo '
						</table>
						<br />'.$this->l('The row in blue is the default combination.').'
						<br />
						'.$this->l('A default combination must be designated for each product.').'
						</td>
						</tr>
					</table>
					<script type="text/javascript">
						var impact = getE(\'attribute_price_impact\');
						var impact2 = getE(\'attribute_weight_impact\');

						var s_attr_group = document.getElementById(\'span_new_group\');
						var s_attr_name = document.getElementById(\'span_new_attr\');
						var s_impact = document.getElementById(\'span_impact\');
						var s_impact2 = document.getElementById(\'span_weight_impact\');

						init_elems();
					</script>';
				}
				else
					echo '<b>'.$this->l('You must save this product before adding combinations').'.</b>';
	}

	public function displayFormFeatures($obj)
	{
		if (!Feature::isFeatureActive())
		{
			$this->displayWarning($this->l('This feature has been disabled, you can active this feature at this page:').' <a href="index.php?tab=AdminPerformance&token='.Tools::getAdminTokenLite('AdminPerformance').'#featuresDetachables">'.$this->l('Performances').'</a>');
			return;
		}

		parent::displayForm();

		if ($obj->id)
		{
			$feature = Feature::getFeatures($this->context->language->id);
			$ctab = '';
			foreach ($feature as $tab)
				$ctab .= 'ccustom_'.$tab['id_feature'].'¤';
			$ctab = rtrim($ctab, '¤');

			echo '
			<table cellpadding="5">
				<tr>
					<td colspan="2">
						<b>'.$this->l('Assign features to this product:').'</b><br />
						<ul style="margin: 10px 0 0 20px;">
							<li>'.$this->l('You can specify a value for each relevant feature regarding this product, empty fields will not be displayed.').'</li>
							<li>'.$this->l('You can either set a specific value, or select among existing pre-defined values you added previously.').'</li>
						</ul>
					</td>
				</tr>
			</table>
			<hr style="width:100%;" /><br />';
			// Header
			$nb_feature = Feature::nbFeatures($this->context->language->id);
			echo '
			<table border="0" cellpadding="0" cellspacing="0" class="table" style="width:900px;">
				<tr>
					<th>'.$this->l('Feature').'</td>
					<th style="width:30%">'.$this->l('Pre-defined value').'</td>
					<th style="width:40%"><u>'.$this->l('or').'</u> '.$this->l('Customized value').'</td>
				</tr>';
			if (!$nb_feature)
				echo '<tr><td colspan="3" style="text-align:center;">'.$this->l('No features defined').'</td></tr>';
			echo '</table>';

			// Listing
			if ($nb_feature)
			{
				echo '
				<table cellpadding="5" style="width: 900px; margin-top: 10px">';

				foreach ($feature as $tab_features)
				{
					$current_item = false;
					$custom = true;
					foreach ($obj->getFeatures() as $tab_products)
						if ($tab_products['id_feature'] == $tab_features['id_feature'])
							$current_item = $tab_products['id_feature_value'];

					$featureValues = FeatureValue::getFeatureValuesWithLang($this->context->language->id, (int)$tab_features['id_feature']);

					echo '
					<tr>
						<td>'.$tab_features['name'].'</td>
						<td style="width: 30%">';

					if (sizeof($featureValues))
					{
						echo '
							<select id="feature_'.$tab_features['id_feature'].'_value" name="feature_'.$tab_features['id_feature'].'_value"
								onchange="$(\'.custom_'.$tab_features['id_feature'].'_\').val(\'\');">
								<option value="0">---&nbsp;</option>';

						foreach ($featureValues as $value)
						{
							if ($current_item == $value['id_feature_value'])
								$custom = false;
							echo '<option value="'.$value['id_feature_value'].'"'.(($current_item == $value['id_feature_value']) ? ' selected="selected"' : '').'>'.substr($value['value'], 0, 40).(Tools::strlen($value['value']) > 40 ? '...' : '').'&nbsp;</option>';
						}

						echo '</select>';
					}
					else
						echo '<input type="hidden" name="feature_'.$tab_features['id_feature'].'_value" value="0" /><span style="font-size: 10px; color: #666;">'.$this->l('N/A').' - <a href="index.php?tab=AdminFeatures&addfeature_value&id_feature='.(int)$tab_features['id_feature'].'&token='.Tools::getAdminToken('AdminFeatures'.(int)(Tab::getIdFromClassName('AdminFeatures')).(int)$this->context->employee->id).'" style="color: #666; text-decoration: underline;">'.$this->l('Add pre-defined values first').'</a></span>';

					echo '
						</td>
						<td style="width:40%" class="translatable">';
					$tab_customs = ($custom ? FeatureValue::getFeatureValueLang($current_item) : array());
					foreach ($this->_languages as $language)
						echo '
							<div class="lang_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultFormLanguage ? 'block' : 'none').'; float: left;">
								<textarea class="custom_'.$tab_features['id_feature'].'_" name="custom_'.$tab_features['id_feature'].'_'.$language['id_lang'].'" cols="40" rows="1"
									onkeyup="if (isArrowKey(event)) return ;$(\'#feature_'.$tab_features['id_feature'].'_value\').val(0);" >'.htmlentities(Tools::getValue('custom_'.$tab_features['id_feature'].'_'.$language['id_lang'], FeatureValue::selectLang($tab_customs, $language['id_lang'])), ENT_COMPAT, 'UTF-8').'</textarea>
							</div>';
					echo '
						</td>
					</tr>';
				}
				echo '
				<tr>
					<td style="height: 50px; text-align: center;" colspan="3"><input type="submit" name="submitProductFeature" id="submitProductFeature" value="'.$this->l('Save modifications').'" class="button" /></td>
				</tr>';
			}
			echo '</table>
			<hr style="width:100%;" />
			<div style="text-align:center;">
				<a href="index.php?tab=AdminFeatures&addfeature&token='.Tools::getAdminToken('AdminFeatures'.(int)(Tab::getIdFromClassName('AdminFeatures')).(int)$this->context->employee->id).'" onclick="return confirm(\''.$this->l('You will lose all modifications not saved, you may want to save modifications first?', __CLASS__, true, false).'\');"><img src="../img/admin/add.gif" alt="new_features" title="'.$this->l('Add a new feature').'" />&nbsp;'.$this->l('Add a new feature').'</a>
			</div>';
		}
		else
			echo '<b>'.$this->l('You must save this product before adding features').'.</b>';
	}

	public function haveThisAccessory($accessoryId, $accessories)
	{
		foreach ($accessories as $accessory)
			if ((int)($accessory['id_product']) == (int)($accessoryId))
				return true;
		return false;
	}

	private function displayPack(Product $obj)
	{
		$boolPack = (($obj->id && Pack::isPack($obj->id)) || Tools::getValue('ppack')) ? true : false;
		$packItems = $boolPack ? Pack::getItems($obj->id, $this->context->language->id) : array();

		echo '
		<tr>
			<td>
				<input type="checkbox" name="ppack" id="ppack" value="1"'.($boolPack ? ' checked="checked"' : '').' onclick="$(\'#ppackdiv\').slideToggle();" />
				<label class="t" for="ppack">'.$this->l('Pack').'</label>
			</td>
			<td>
				<div id="ppackdiv" '.($boolPack ? '' : ' style="display: none;"').'>
					<div id="divPackItems">';
		foreach ($packItems as $packItem)
			echo $packItem->pack_quantity.' x '.$packItem->name.'<span onclick="delPackItem('.$packItem->id.');" style="cursor: pointer;"><img src="../img/admin/delete.gif" /></span><br />';
		echo '		</div>
					<input type="hidden" name="inputPackItems" id="inputPackItems" value="';
					if (Tools::getValue('inputPackItems'))
						echo Tools::getValue('inputPackItems');
					else
						foreach ($packItems as $packItem)
							echo $packItem->pack_quantity.'x'.$packItem->id.'-';
					echo '" />
					<input type="hidden" name="namePackItems" id="namePackItems" value="';
					if (Tools::getValue('namePackItems'))
						echo Tools::getValue('namePackItems');
					else
					foreach ($packItems as $packItem)
						echo $packItem->pack_quantity.' x '.$packItem->name.'¤';
					echo '" />
					<input type="hidden" size="2" id="curPackItemId" />

					<p class="clear">'.$this->l('Begin typing the first letters of the product name, then select the product from the drop-down list:').'
					<br />'.$this->l('You cannot add downloadable products to a pack.').'</p>
					<input type="text" size="25" id="curPackItemName" />
					<input type="text" name="curPackItemQty" id="curPackItemQty" value="1" size="1" />
					<script language="javascript">
					'.$this->addPackItem().'
					'.$this->delPackItem().'

					</script>
					<span onclick="addPackItem();" style="cursor: pointer;"><img src="../img/admin/add.gif" alt="'.$this->l('Add an item to the pack').'" title="'.$this->l('Add an item to the pack').'" /></span>
				</td>
			</div>
		</tr>';
		// param multipleSeparator:'||' ajouté à cause de bug dans lib autocomplete
		echo '<script type="text/javascript">
								urlToCall = null;
								/* function autocomplete */
								$(function() {
									$(\'#curPackItemName\')
										.autocomplete(\'ajax_products_list.php\', {
											delay: 100,
											minChars: 1,
											autoFill: true,
											max:20,
											matchContains: true,
											mustMatch:true,
											scroll:false,
											cacheLength:0,
											multipleSeparator:\'||\',
											formatItem: function(item) {
												return item[1]+\' - \'+item[0];
											}
										}).result(function(event, item){
											$(\'#curPackItemId\').val(item[1]);
										});
										$(\'#curPackItemName\').setOptions({
											extraParams: {excludeIds : getSelectedIds(), excludeVirtuals : 1}
										});

								});


								function getSelectedIds()
								{
									// input lines QTY x ID-
									var ids = '. $obj->id.'+\',\';
									ids += $(\'#inputPackItems\').val().replace(/\\d+x/g, \'\').replace(/\-/g,\',\');
									ids = ids.replace(/\,$/,\'\');

									return ids;

								}

								function getAccessorieIds()
								{
									var ids = '. $obj->id.'+\',\';
									ids += $(\'#inputAccessories\').val().replace(/\\-/g,\',\').replace(/\\,$/,\'\');
									ids = ids.replace(/\,$/,\'\');

									return ids;
								}

			</script>';

	}

	private function addPackItem()
	{
		return '
			function addPackItem()
			{
				if ($(\'#curPackItemId\').val() == \'\' || $(\'#curPackItemName\').val() == \'\')
				{
					alert(\''.$this->l('Thanks to select at least one product.').'\');
					return false;
				}
				else if ($(\'#curPackItemId\').val() == \'\' || $(\'#curPackItemQty\').val() == \'\')
				{
					alert(\''.$this->l('Thanks to set a quantity to add a product.').'\');
					return false;
				}

			var lineDisplay = $(\'#curPackItemQty\').val()+ \'x \' +$(\'#curPackItemName\').val();

			var divContent = $(\'#divPackItems\').html();
			divContent += lineDisplay;
			divContent += \'<span onclick="delPackItem(\' + $(\'#curPackItemId\').val() + \');" style="cursor: pointer;"><img src="../img/admin/delete.gif" /></span><br />\';

			// QTYxID-QTYxID
			var line = $(\'#curPackItemQty\').val()+ \'x\' +$(\'#curPackItemId\').val();


			$(\'#inputPackItems\').val($(\'#inputPackItems\').val() + line  + \'-\');
			$(\'#divPackItems\').html(divContent);
				$(\'#namePackItems\').val($(\'#namePackItems\').val() + lineDisplay + \'Â¤\');

			$(\'#curPackItemId\').val(\'\');
			$(\'#curPackItemName\').val(\'\');

			$(\'#curPackItemName\').setOptions({
				extraParams: {excludeIds :  getSelectedIds()}
			});
			}
		';
	}

	private function delPackItem()
	{
		return '
		function delPackItem(id)
		{
			var reg = new RegExp(\'-\', \'g\');
			var regx = new RegExp(\'x\', \'g\');

			var div = getE(\'divPackItems\');
			var input = getE(\'inputPackItems\');
			var name = getE(\'namePackItems\');
			var select = getE(\'curPackItemId\');
			var select_quantity = getE(\'curPackItemQty\');

			var inputCut = input.value.split(reg);
			var nameCut = name.value.split(new RegExp(\'¤\', \'g\'));

			input.value = \'\';
			name.value = \'\';
			div.innerHTML = \'\';

			for (var i = 0; i < inputCut.length; ++i)
				if (inputCut[i])
				{
					var inputQty = inputCut[i].split(regx);
					if (inputQty[1] != id)
					{
						input.value += inputCut[i] + \'-\';
						name.value += nameCut[i] + \'¤\';
						div.innerHTML += nameCut[i] + \' <span onclick="delPackItem(\' + inputQty[1] + \');" style="cursor: pointer;"><img src="../img/admin/delete.gif" /></span><br />\';
					}
				}

			$(\'#curPackItemName\').setOptions({
				extraParams: {excludeIds :  getSelectedIds()}
			});
		}';
	}

	public function updatePackItems($product)
	{
		Pack::deleteItems($product->id);

		// lines format: QTY x ID-QTY x ID
		if (Tools::getValue('ppack') && $items = Tools::getValue('inputPackItems') && sizeof($lines = array_unique(explode('-', $items))))
		{
			foreach ($lines as $line)
			{
				// line format QTY x ID
				list($qty, $item_id) = explode('x', $line);
				if ($qty > 0 && isset($item_id))
				{
					if (!Pack::addItem((int)($product->id), (int)($item_id), (int)($qty)))
						return false;
				}
			}
		}
		return true;
	}

	public function getL($key)
	{
		$trad = array(
			'Default category:' => $this->l('Default category:'),
			'Catalog:' => $this->l('Catalog:'),
			'Consider changing the default category.' => $this->l('Consider changing the default category.'),
			'ID' => $this->l('ID'),
			'Name' => $this->l('Name'),
			'Mark all checkbox(es) of categories in which product is to appear' => $this->l('Mark all checkbox(es) of categories in which product is to appear')
		);
		return $trad[$key];
	}

	/**
	 * Load class object using identifier in $_GET (if possible)
	 * otherwise return an empty object, or die
	 *
	 * @param boolean $opt Return an empty object if load fail
	 * @return object
	 */
	protected function loadObject($opt = false)
	{
		if ($id = (int)(Tools::getValue($this->identifier)) AND Validate::isUnsignedId($id))
		{
			if (!$this->_object)
				$this->_object = new $this->className($id);
			if (Validate::isLoadedObject($this->_object))
				return $this->_object;
			$this->_errors[] = Tools::displayError('Object cannot be loaded (not found)');
		}
		else if ($opt)
		{
			$this->_object = new $this->className();
			return $this->_object;
		}
		else
			$this->_errors[] = Tools::displayError('Object cannot be loaded (identifier missing or invalid)');

		$this->displayErrors();
	}
}
