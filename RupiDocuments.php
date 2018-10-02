<?php

namespace RupiDocuments;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Plugin;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\UninstallContext;

class RupiDocuments extends Plugin
{
  public function install(InstallContext $context)
  {

      $entityManager = $this->container->get('models');
      $dbConn = $this->container->get('dbal_connection');

      $content = "{sOrderNumber}";

      $stmt = $dbConn->query("INSERT INTO s_core_config_mails (name, frommail, fromname, subject, content, ishtml, mailtype, dirty)
        VALUES ('RupiDocuments', '{config name=mail}', '{config name=shopName}', 'Rechnung zu Bestellung', '', 0, 1, 1)");

      $service = $this->container->get('shopware_attribute.crud_service');
      $service->update('s_order_attributes', 'invoice_send', 'boolean', [
            'label' => 'Rechnung versendet?',
            'supportText' => 'Wenn die Checkbox aktiv ist, wurde die Rechnungskopie versendet',
            'translatable' => false,
            'displayInBackend' => true,
            'position' => 100,
            'custom' => false
        ]);

      parent::install($context);
  }

  public function uninstall(UninstallContext $context)
  {
        $dbConn = $this->container->get('dbal_connection');

        $dbConn->executeQuery('DELETE FROM s_core_config_mails WHERE `name` = ?', ['RupiDocuments']);

        $service = $this->container->get('shopware_attribute.crud_service');
        $service->delete('s_order_attributes', 'invoice_send');
  }
}
