<?php
namespace RupiDocuments\Subscriber;
/**
 * DocumentMailer
 *
 * @category  Shopware
 * @copyright Copyright (c), Andreas Ruprecht (https://andreasruprecht.at)
 */

use Enlight\Event\SubscriberInterface;

class DocumentMailer implements SubscriberInterface
{
  const LOG_ACTION_ORDER_STATUS_CHANGE = 'OrderStatusChange';
  /**
   * Subscribe to events
   *
   * @return array
   */
  public static function getSubscribedEvents()
  {
      return [
          'Shopware\Models\Order\Order::preUpdate' => 'onPreUpdateOrder',
          'Shopware\Models\Order\Order::postUpdate' => 'onPostUpdateOrder',
          'Shopware\Models\Order\Order::preRemove' => 'onRemoveOrder',
          'Shopware\Models\Order\Detail::preRemove' => 'onPreRemovePosition',
          'Shopware\Models\Order\Detail::postRemove' => 'onPostRemovePosition',
      ];
  }

    /**
   * @param \Enlight_Event_EventArgs $arguments
   *
   * @return void
   */
  public function onPostUpdateOrder(\Enlight_Event_EventArgs $arguments)
  {
      $order = $arguments->get('entity');
      $orderId = $order->getId();
      $orderDispatch = $order->getShipping()->getCountry()->getIso();
      $orderNumber = $order->getNumber();

      $pluginConfig = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('RupiDocuments');

      $countries = explode(";",$pluginConfig['countries']);

      if(in_array($orderDispatch, $countries)){
        $document = Shopware()->Db()->fetchRow(
            "SELECT * FROM s_order_documents WHERE orderID = ? AND type = ? ORDER BY ID DESC LIMIT 1",
            array($orderId, '1')
        );


      if(in_array($order->getOrderStatus()->getId(), $pluginConfig['orderStates'])){
          $fileName = Shopware()->DocPath().'files/documents/'.$document['hash'].".pdf";

          if(file_exists($fileName)){
            $sendMail = $this->sendDocument($fileName, $document['docID'], $orderNumber);
            $query = Shopware()->Db()->fetchRow(
                "SELECT * FROM s_order_attributes WHERE orderID = ? ORDER BY ID DESC LIMIT 1",
                array($orderId));
              if(!$query){
                Shopware()->Db()->query("INSERT INTO s_order_attributes (orderId, invoice_send) VALUES ('{$orderId}', '1')");
              } else {
                Shopware()->Db()->query("UPDATE s_order_attributes SET invoice_send = 1 WHERE orderId = '{$orderId}'");
              }

          }
        }
      }
  }

  public function sendDocument($document, $invoice, $order)
  {
    $pluginConfig = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('RupiDocuments');

    $context = [
      'sOrderNumber' => $order,
      'sInvoice' => $invoice,
    ];

    $content = file_get_contents($document);
    $mail = Shopware()->TemplateMail()->createMail('RupiDocuments', $context);
    $mail->From = Shopware()->Config()->Mail;
    $mail->FromName = Shopware()->Config()->Mail;
    $mail->ClearAddresses();

    $recipient = explode(";", $pluginConfig['recipient']);
    foreach($recipient as $r){
      $mail->AddAddress($r, '');
    }
    $attachment = new \Zend_Mime_Part($content);
    $attachment->type = 'application/pdf';
    $attachment->disposition = \Zend_Mime::DISPOSITION_ATTACHMENT;
    $attachment->encoding = \Zend_Mime::ENCODING_BASE64;
    $attachment->filename = $filename;
    $mail->AddAttachment($attachment);

    if($mail->send()){
      return true;
    }

    return false;
  }

}
