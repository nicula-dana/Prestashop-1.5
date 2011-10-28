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
*  @version  Release: $Revision: 9565 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class AdminSupplierOrdersControllerCore extends AdminController
{
	public function __construct()
	{
		$this->context = Context::getContext();
	 	$this->table = 'supplier_order_state';
	 	$this->className = 'SupplierOrderState';
	 	$this->colorOnBackground = true;
		$this->lang = true;

		$this->fieldsDisplay = array(
			'id_state' => array(
				'title' => $this->l('ID'),
				'align' => 'center',
				'width' => 40,
				'widthColumn' => 40,
				'search' => false,
			),
			'name' => array(
				'title' => $this->l('Name'),
				'width' => 130,
			),
			'editable' => array(
				'title' => $this->l('Editable?'),
				'align' => 'center',
				'icon' => array(
					'1' => 'enabled.gif',
					'0' => 'disabled.gif'
				),
				'type' => 'bool',
				'orderby' => false
			),
			'delivery_note' => array(
				'title' => $this->l('Delivery note available?'),
				'align' => 'center',
				'icon' => array(
					'1' => 'enabled.gif',
					'0' => 'disabled.gif'
				),
				'type' => 'bool',
				'orderby' => false
			),
			'receipt_state' => array(
				'title' => $this->l('Is a delivery state?'),
				'align' => 'center',
				'icon' => array(
					'1' => 'enabled.gif',
					'0' => 'disabled.gif'
				),
				'type' => 'bool',
				'orderby' => false
			),
			'pending_receipt' => array(
				'title' => $this->l('Is a pending receipt state?'),
				'align' => 'center',
				'icon' => array(
					'1' => 'enabled.gif',
					'0' => 'disabled.gif'
				),
				'type' => 'bool',
				'orderby' => false
			),
		);

		parent::__construct();
	}

	/**
	 * AdminController::init() override
	 * @see AdminController::init()
	 */
	public function init()
	{
		parent::init();

		if (Tools::isSubmit('addsupplier_order')
			|| Tools::isSubmit('submitAddsupplier_order')
			|| (
				Tools::isSubmit('updatesupplier_order')
				&& Tools::isSubmit('id_supplier_order')
		))
		{
			// override table, land, className and identifier for the current controller
		 	$this->table = 'supplier_order';
		 	$this->className = 'SupplierOrder';
		 	$this->identifier = 'id_supplier_order';
		 	$this->lang = false;

			$this->action = 'new';
			$this->display = 'add';

			if (Tools::isSubmit('updatesupplier_order'))
				if ($this->tabAccess['edit'] === '1')
					$this->display = 'edit';
				else
					$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}

	}

	/**
	 * AdminController::initForm() override
	 * @see AdminController::initForm()
	 */
	public function initForm()
	{
		if (Tools::isSubmit('addsupplier_order_state'))
		{
			$this->fields_form = array(
				'legend' => array(
					'title' => $this->l('Supplier Order State'),
					'image' => '../img/admin/edit.gif'
				),
				'input' => array(
					array(
						'type' => 'text',
						'lang' => true,
						'attributeLang' => 'name',
						'label' => $this->l('Name:'),
						'name' => 'name',
						'size' => 50,
						'required' => true
					),
					array(
						'type' => 'color',
						'label' => $this->l('Back office color:'),
						'name' => 'color',
						'size' => 20,
						'p' => $this->l('Back office background will be displayed in this color. HTML colors only (e.g.,').' "lightblue", "#CC6600")'
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Is the order editable in this state?:'),
						'name' => 'editable',
						'required' => true,
						'class' => 't',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'p' => $this->l('You have to define if it is possible to edit the order in this state.
										An editable order is an order not valid to send to the supplier.')
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Is the delivery note function is available in this state?:'),
						'name' => 'delivery_note',
						'required' => true,
						'class' => 't',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'p' => $this->l('You have to define if it is possible to generate the delivery note of the order in this state.
										The order has to be valid to use this function.')
					),
					array(
						'type' => 'radio',
						'label' => $this->l('This state corresponds to a delivery state ?:'),
						'name' => 'receipt_state',
						'required' => true,
						'class' => 't',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'p' => $this->l('You have to define if this state correspond to a product receipt on this order (partial or complete).
										This permit to know if the concerned products have to be added in stock.')
					),
					array(
						'type' => 'radio',
						'label' => $this->l('This state corresponds to a product pending receipt ?:'),
						'name' => 'pending_receipt',
						'required' => true,
						'class' => 't',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'p' => $this->l('You have to define if some products are pending receipt in this state.')
					),
				),
				'submit' => array(
					'title' => $this->l('   Save   '),
					'class' => 'button'
				)
			);
		}

		if (Tools::isSubmit('addsupplier_order')
			|| Tools::isSubmit('updatesupplier_order')
			|| Tools::isSubmit('submitAddsupplier_order')
			|| Tools::isSubmit('submitUpdatesupplier_order'))
		{
			//$this->context->controller->addJqueryUI('ui.datepicker');
			$this->addJqueryUI('ui.datepicker');

			//get warehouses list
			$warehouses = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
				SELECT `id_warehouse`, CONCAT(`reference`, " - ", `name`) as name
				FROM `'._DB_PREFIX_.'warehouse`
				ORDER BY `reference` ASC');

			//get currencies list
			$currencies = Currency::getCurrencies();

			//get suppliers list
			$suppliers = Supplier::getSuppliers();

			$this->fields_form = array(
				'legend' => array(
					'title' => $this->l('Supplier Order Management'),
					'image' => '../img/admin/edit.gif'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Reference:'),
						'name' => 'reference',
						'size' => 50,
						'required' => true
					),
					array(
						'type' => 'select',
						'label' => $this->l('Supplier:'),
						'name' => 'id_supplier',
						'required' => true,
						'options' => array(
							'query' => $suppliers,
							'id' => 'id_supplier',
							'name' => 'name'
						),
						'p' => $this->l('Select the supplier associated to this order')
					),
					array(
						'type' => 'select',
						'label' => $this->l('Warehouse:'),
						'name' => 'id_warehouse',
						'required' => true,
						'options' => array(
							'query' => $warehouses,
							'id' => 'id_warehouse',
							'name' => 'name'
						),
						'p' => $this->l('Select the warehouse where you want to receive the products from this order')
					),
					array(
						'type' => 'select',
						'label' => $this->l('Currency:'),
						'name' => 'id_currency',
						'required' => true,
						'options' => array(
							'query' => $currencies,
							'id' => 'id_currency',
							'name' => 'name'
						),
						'p' => $this->l('The currency of the order'),
					),
					array(
						'type' => 'date',
						'label' => $this->l('Delivery date:'),
						'name' => 'date_delivery_expected',
						'size' => 20,
						'required' => true,
						'p' => $this->l('You can specify an expected delivery date for this order'),
					),
					array(
						'type' => 'text',
						'label' => $this->l('Global discount rate on this order:'),
						'name' => 'discount_rate',
						'size' => 5,
						'required' => true,
						'p' => $this->l('You can specify a global discount rate for the order'),
					),
				),
				'submit' => array(
					'title' => $this->l('   Save   '),
					'class' => 'button'
				)
			);
		}

		return parent::initForm();
	}

	/**
	 * AdminController::getList() override
	 * @see AdminController::getList()
	 */
	public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
	{
		parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

		// actions filters on supplier orders list
		if ($this->table == 'supplier_order')
		{
			$nb_items = count($this->_list);

			for ($i = 0; $i < $nb_items; $i++)
			{
				// if the current state doesn't allow order edit, skip the action
				if ($this->_list[$i]['editable'] == 0)
					$this->addRowActionSkipList('edit', $this->_list[$i]['id_supplier_order']);
			}
		}
	}

	/**
	 * AdminController::initList() override
	 * @see AdminController::initList()
	 */
	public function initList()
	{
		$this->displayInformation($this->l('This interface allows you to manage supplier orders.').'<br />');
		$this->displayInformation($this->l('Also, it allows you to add and edit your own supplier order states.'));

		// access
		if (!($this->tabAccess['add'] === '1'))
			$this->no_add = true;

		//no link on list rows
		$this->list_no_link = true;

		/*
		 * Manage default list
		 */
		$this->addRowAction('edit');
		$this->addRowAction('delete');
		$this->addRowActionSkipList('edit', array(1, 2, 3, 4, 5, 6));
		$this->addRowActionSkipList('delete', array(1, 2, 3, 4, 5, 6));

		$first_list = '<hr /><h2>'.$this->l('Suppliers Orders States').'</h2>';
		$first_list .= parent::initList();

		/*
		 * Manage second list
		 */
		// reset actions, toolbar and query vars
		$this->actions = array();
		$this->list_skip_actions = array();
		$this->toolbar_btn = array();
		unset($this->_select, $this->_join, $this->_group, $this->_filterHaving, $this->_filter);

		// override table, land, className and identifier for the current controller
	 	$this->table = 'supplier_order';
	 	$this->className = 'SupplierOrder';
	 	$this->identifier = 'id_supplier_order';
	 	$this->lang = false;

		$this->addRowAction('edit');

	 	// test if a filter is applied for this list
		if (Tools::isSubmit('submitFilter'.$this->table) || $this->context->cookie->{'submitFilter'.$this->table} !== false)
			$this->filter = true;

		// test if a filter reset request is required for this list
		if (isset($_POST['submitReset'.$this->table]))
			$this->action = 'reset_filters';
		else
			$this->action = '';

		// redifine fields display
		$this->fieldsDisplay = array(
			'reference' => array(
				'title' => $this->l('Order Reference'),
				'width' => 100,
				'havingFilter' => true
			),
			'employee' => array(
				'title' => $this->l('Employee'),
				'width' => 100,
				'havingFilter' => true
			),
			'supplier' => array(
				'title' => $this->l('Supplier'),
				'width' => 100,
				'filter_key' => 's!name'
			),
			'warehouse' => array(
				'title' => $this->l('Warehouse'),
				'width' => 100,
				'filter_key' => 'w!name'
			),
			'date_add' => array(
				'title' => $this->l('Creation date'),
				'width' => 50,
				'align' => 'right',
				'type' => 'datetime',
				'havingFilter' => true
			),
			'date_upd' => array(
				'title' => $this->l('Last modification date'),
				'width' => 50,
				'align' => 'right',
				'type' => 'datetime',
				'havingFilter' => true
			),
			'date_delivery_expected' => array(
				'title' => $this->l('Delivery date'),
				'width' => 50,
				'align' => 'right',
				'type' => 'datetime',
			),
			'state' => array(
				'title' => $this->l('State'),
				'width' => 100,
				'filter_key' => 'st!name'
			),
		);

		// make new query
		$this->_select = '
			CONCAT(e.lastname, \' \', e.firstname) AS employee,
			s.name AS supplier,
			CONCAT(w.reference, \' \', w.name) AS warehouse,
			stl.name AS state,
			st.delivery_note,
			st.editable,
			st.receipt_state,
			st.color AS color';

		$this->_join = 'LEFT JOIN `'._DB_PREFIX_.'supplier_order_state_lang` stl ON
						(
							a.id_supplier_order_state = stl.id_supplier_order_state
							AND stl.id_lang = '.(int)$this->context->language->id.'
						)
						LEFT JOIN `'._DB_PREFIX_.'supplier_order_state` st ON a.id_supplier_order_state = st.id_supplier_order_state
						LEFT JOIN `'._DB_PREFIX_.'supplier` s ON a.id_supplier = s.id_supplier
						LEFT JOIN `'._DB_PREFIX_.'warehouse` w ON (w.id_warehouse = a.id_warehouse)
						LEFT JOIN `'._DB_PREFIX_.'employee` e ON (e.id_employee = a.id_employee)';

		// call postProcess() for take care about actions and filters
		$this->postProcess();

		// init the toolbar according to the current list
		$this->initToolbar();

		// generate the second list
		$second_list = parent::initList();

		// reset all query vars
		unset($this->_select, $this->_join, $this->_group, $this->_filterHaving, $this->_filter);

		// return the two lists
		return $second_list.$first_list;
	}

	/**
	 * AdminController::postProcess() override
	 * @see AdminController::postProcess()
	 */
	public function postProcess()
	{
		// Checks access
		if (Tools::isSubmit('submitAddsupplier_order') && !($this->tabAccess['add'] === '1'))
			$this->_errors[] = Tools::displayError('You do not have the required permission to add a supplier order.');

		// Global checks when add / remove / transfer product
		if (Tools::isSubmit('submitAddsupplier_order'))
		{
			$this->action = 'save';

			// get supplier ID
			$id_supplier = (int)Tools::getValue('id_supplier', 0);
			if ($id_supplier <= 0 || !Supplier::supplierExists($id_supplier))
				$this->_errors[] = Tools::displayError('The selected supplier is not valid.');

			// get warehouse id
			$id_warehouse = (int)Tools::getValue('id_warehouse', 0);
			if ($id_warehouse <= 0 || !Warehouse::exists($id_warehouse))
				$this->_errors[] = Tools::displayError('The selected warehouse is not valid.');

			// get currency id
			$id_currency = (int)Tools::getValue('id_currency', 0);
			if ($id_currency <= 0 || ( !($result = Currency::getCurrency($id_currency)) || empty($result) ))
				$this->_errors[] = Tools::displayError('The selected currency is not valid.');

			// specify employee
			$_POST['id_employee'] = $this->context->employee->id;

			// specify initial state
			$_POST['id_supplier_order_state'] = 1; //defaut creation state

			// specify global reference currency
			$_POST['id_ref_currency'] = $this->context->currency->id;
		}

		parent::postProcess();
	}
}