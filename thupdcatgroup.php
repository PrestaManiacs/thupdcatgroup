<?php
/**
 * 2006-2022 THECON SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    THECON SRL <contact@thecon.ro>
 * @copyright 2006-2022 THECON SRL
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Thupdcatgroup extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'thupdcatgroup';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Presta Maniacs';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mass Update for Category Groups Access');
        $this->description = $this->l('Massively updates the access of customer groups for product categories.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (!parent::install() || !$this->registerHooks()) {
            return false;
        }

        return true;
    }

    public function registerHooks()
    {
        if (!$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $message = '';
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submit_th_reindex')) == true) {
            $this->reindexAccessGroups();
            if (count($this->_errors)) {
                $message = $this->displayError($this->_errors);
            } else {
                $message = $this->displayConfirmation($this->l('Successfully update!'));
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $maniacs = $this->context->smarty->fetch($this->local_path.'views/templates/admin/maniacs.tpl');

        return $message.$maniacs.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitThupdcatgroupModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type'  => 'categories',
                        'label' => $this->l('Categories:'),
                        'name'  => 'THUPDCATGROUP_CATEGORIES',
                        'form_group_class' => 'form_group_filter_category',
                        'hint' => $this->l('Select only main categories, subcategories will be displayed automatically'),
                        'tree'  => array(
                            'id'  => 'categories-tree',
                            'use_checkbox' => true,
                            'use_search' => true,
                            'selected_categories' => array()
                        ),
                        'required' => true
                    ),
                    array(
                        'type' => 'checkbox',
                        'label' => $this->l('Customers groups:'),
                        'name' => 'THUPDCATGROUP_C_GROUP',
                        'values' => array(
                            'query' => Group::getGroups($this->context->language->id),
                            'id' => 'id_group',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'th_reindexing_group_access',
                        'name' => '',
                        'th_ps_version' => $this->getPsVersion(),
                        'th_ps_sub_version' => $this->getPsSubVersion(),
                        'th_icon_path' => $this->context->shop->getBaseURL(true, true).'modules/'.$this->name.'/views/img/reload-icon.png'
                    )
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $data = array(
            'THUPDCATGROUP_CATEGORIES' => Tools::getValue('THUPDCATGROUP_CATEGORIES', Configuration::get('THUPDCATGROUP_CATEGORIES')),
        );

        $groups = Group::getGroups($this->context->language->id);
        foreach ($groups as $group) {
            $key = 'THUPDCATGROUP_C_GROUP_'.$group['id_group'];
            $data[$key] = Tools::getValue($key);
        }

        return $data;
    }

    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function reindexAccessGroups()
    {
        $form_values = $this->getConfigFormValues();
        $groups = array();

        foreach (array_keys($form_values) as $key) {
            if (Tools::getValue($key)) {
                $groups[] = Tools::substr($key, -1);
            }
        }

        if (!is_array($form_values['THUPDCATGROUP_CATEGORIES'])) {
            $this->_errors[] = 'Please select at least one category!';
            return false;
        }

        foreach ($form_values['THUPDCATGROUP_CATEGORIES'] as $category_id) {
            $category_object = new Category($category_id);
            $category_object->updateGroup($groups);
        }

        return true;
    }

    public function getPsVersion()
    {
        $full_version = _PS_VERSION_;
        return explode(".", $full_version)[1];
    }

    public function getPsSubVersion()
    {
        $full_version = _PS_VERSION_;
        return explode(".", $full_version)[2];
    }
}
