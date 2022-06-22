<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\Json\JsonEncodeException;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Exception\NotFoundError;

trait RestApiMethods
{
    protected function runForApi($callback)
    {
        try {
            $callback();
        } catch (NotFoundError $e) {
            $this->sendJsonError($e->getMessage(), 404);
        } catch (\Throwable $e) {
            $this->sendJsonError($e);
        }
    }

    /**
     * @param \Throwable|string $error
     * @param int $code
     */
    protected function sendJsonError($error, $code = 500)
    {
        $data = [];

        if ($error instanceof \Exception) {
            $message = $error->getMessage();
            $data['trace'] = iconv('UTF-8', 'UTF-8//IGNORE', $error->getTraceAsString());
        } else {
            $message = (string) $error;
        }

        $data['error'] = iconv('UTF-8', 'UTF-8//IGNORE', $message);

        $this->sendJsonResponse($data, $code);
    }

    protected function sendJsonResponse($object, $code = 200)
    {
        /** @var $this CompatController */
        $this->getResponse()->setHttpResponseCode($code);
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        try {
            echo JsonString::encode($object, JSON_PRETTY_PRINT);
        } catch (JsonEncodeException $e) {
            $this->sendJsonError($e);
        }
        exit; // TODO: shutdown
    }

    protected function showApiOnly()
    {
        /** @var $this CompatController */
        $this->addSingleTab($this->translate('Error'));
        $this->addTitle($this->translate('API only'));
        $this->content()->add(Hint::error($this->translate('This URL is available for API requests only')));
    }
}
