<?php

namespace OCA\YumiSignNxtC\Notification;

use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier
{
    protected $factory;
    protected $url;

    public function __construct(IFactory $factory, IURLGenerator $URLGenerator)
    {
        $this->factory = $factory;
        $this->url = $URLGenerator;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     * @return string
     */
    public function getID(): string
    {
        return YumiSignApp::APP_ID;
    }

    /**
     * Human readable name describing the notifier
     * @return string
     */
    public function getName(): string
    {
        return $this->factory->get(YumiSignApp::APP_ID)->t('Add yumisign_nextcloud');
    }

    /**
     * @param INotification $notification
     * @param string $languageCode The code of the language that should be used to prepare the notification
     */
    public function prepare(INotification $notification, string $languageCode): INotification
    {
        if ($notification->getApp() !== YumiSignApp::APP_ID) {
            // Not my app
            throw new \InvalidArgumentException();
        }
        $l = $this->factory->get(YumiSignApp::APP_ID, $languageCode);

        // $parameters = $notification->getSubjectParameters();
        $notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(YumiSignApp::APP_ID, 'YumiSign.png')));

        /**
         * Set rich subject, see https://github.com/nextcloud/server/issues/1706 for mor information
         * and https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php
         * for a list of defined objects and their parameters.
         */

        $parameters = $notification->getSubjectParameters();
        $subject = ($parameters['code'] ?  '{message}' :  'YumiSign exception; contact your administrator. {message}');
        $subject = str_replace(['{code}', '{message}', '{status}'], $parameters, $subject);

        $notification->setParsedSubject($subject);
        $notification->setRichSubject($l->t($subject), [
            YumiSignApp::APP_ID => [
                'type'  => 'highlight',
                'id'    => 'bstark',
                'name'  => 'Ben Stark'
            ]
        ]);

        return $notification;
    }
}
