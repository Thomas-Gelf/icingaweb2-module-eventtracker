<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\Json\JsonString;
use Icinga\Module\Eventtracker\Configuration;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\Input\RestApiInput;
use Psr\Log\NullLogger;

use function Clue\React\Block\await as block_await;

class EventController extends Controller
{
    use AsyncControllerHelper;
    use RestApiMethods;

    protected $requiresAuthentication = false;

    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->showApiOnly();
            return;
        }

        $this->runForApi(function () {
            $this->createEvent();
        });
    }

    protected function createEvent()
    {
        $token = null;
        foreach ($this->getServerRequest()->getHeader('Authorization') as $line) {
            if (preg_match('/^Bearer\s+([A-z0-9-]+)$/', $line, $match)) {
                $token = $match[1];
            }
        }
        if ($token === null) {
            $this->sendJsonError('Bearer token is required', 401);
            return;
        }

        $store = new ConfigStore($this->db(), new NullLogger());
        $input = $this->findInputForToken($store, $token);
        if ($input === null) {
            $this->sendJsonError('Bearer token is not valid', 403);
            return;
        }
        $body = (string) $this->getServerRequest()->getBody();
        if (strlen($body) === 0) {
            $this->sendJsonError('JSON body is required', 400);
        }

        $client = new RemoteClient(Configuration::getSocketPath());
        $accepted = block_await(
            $client->request('event.sendToInput', [
                $input->getUuid(),
                JsonString::decode($body)
            ])
        );
        $response = [
            'success' => $accepted ? 'Event accepted' : 'Request valid, found no related Channel'
        ];

        $this->sendJsonResponse($response);
    }

    /**
     * @param ConfigStore $store
     * @param $token
     * @return RestApiInput|null
     */
    protected function findInputForToken(ConfigStore $store, $token)
    {
        $input = null;

        $inputs = $store->loadInputs([
            'implementation' => 'restApi',
        ]);

        /** @var Input $possibleInput */
        foreach ($inputs as $possibleInput) {
            if ($possibleInput instanceof RestApiInput
                && $possibleInput->getSettings()->get('token') === $token
            ) {
                $input = $possibleInput;
            }
        }

        return $input;
    }
}
