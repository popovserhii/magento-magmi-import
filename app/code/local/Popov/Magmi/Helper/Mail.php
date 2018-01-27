<?php
/**
 * Email notification helper
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 23.12.14 20:17
 */
class Popov_Magmi_Helper_Mail extends Mage_Core_Helper_Abstract {

	const XML_PATH_CALLBACK_RECIPIENT = 'popov_magmi/mail_notification/recipient_email';
	const XML_PATH_CALLBACK_SENDER = 'popov_magmi/mail_notification/sender_email_identity';
	const XML_PATH_CALLBACK_TEMPLATE = 'popov_magmi/mail_notification/email_template';
	const XML_PATH_ENABLED = 'popov_magmi/mail_notification/enabled';

	public function sendNotification($website) {
		if (Mage::getStoreConfig(self::XML_PATH_ENABLED)) {
			$translate = Mage::getSingleton('core/translate');
			/* @var $translate Mage_Core_Model_Translate */
			$translate->setTranslateInline(false);
			try {
				$data['date_run'] = date('Y-m-d H:i',  Mage::getModel('core/date')->timestamp(time()));

				$vatObject = new Varien_Object();
				$vatObject->setData($data);

				$mailTemplate = Mage::getModel('core/email_template');
				/* @var $mailTemplate Mage_Core_Model_Email_Template */
				$mailTemplate
					->setDesignConfig(array('area' => 'frontend'))
					//->setReplyTo($post['email'])
					->sendTransactional(
						//Mage::getStoreConfig(self::XML_PATH_CALLBACK_TEMPLATE),
						//Mage::getStoreConfig(self::XML_PATH_CALLBACK_SENDER),
						$website->getConfig(self::XML_PATH_CALLBACK_TEMPLATE),
						$website->getConfig(self::XML_PATH_CALLBACK_SENDER),
						explode(';', Mage::getStoreConfig(self::XML_PATH_CALLBACK_RECIPIENT)),
						null,
						array('data' => $vatObject, 'website' => $website)
					);

				if (!$mailTemplate->getSentSuccess()) {
					throw new Exception();
				}

				return;
			} catch (Exception $e) {
				$translate->setTranslateInline(true);

				Mage::getSingleton('customer/session')->addError(Mage::helper('popov_magmi')
					->__('Unable to submit your request. Please, try again later'));

				return;
			}
		}
	}
}
