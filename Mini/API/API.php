<?php

namespace Mini\API;

use \Mini\Model\Model;
use \Mini\Model\Bot;
use \Mini\Model\Type;
use \Slim\Slim;

use Mini\Model\BotListDescriptor;
use Mini\Model\TypeListDescriptor;

class API {
    const LIMIT = 100;
    const LAST_MODIFIED = 1525540985; // 2018-05-05T19:23:XX+0200
    const NAME_PREFIX = 'v2';

    const PARAM_LIMIT = 'limit';
    const PARAM_OFFSET = 'offset';
    const PARAM_DISABLED = 'inactive';
    const PARAM_MULTICHANNEL = 'multiChannel';
    const PARAM_TYPE = 'type';
    const PARAM_IDS = 'ids';

    const LINK_SELF = 'canonical';
    const LINK_DOCUMENTATION = 'help';
    const LINK_WEB = 'alternate';
    const LINK_NEXT = 'next';
    const LINK_PREV = 'prev';

    const PROP_LINKS = '_links';

    /** @var Model $model */
    private $model;

    /** @var Slim $app */
    private $app;

    /** @var array $result */
    private $result = [];

    public function __construct(Model $model, Slim $app)
    {
        $this->model = $model;
        $this->app = $app;

        $this->app->contentType('application/json;charset=utf-8');
        $this->app->expires('+1 day');

        $this->app->group('/v2', function() {
            $this->app->group('/bot', function() {
                $this->addEndpoint('/', function() {
                    $type = $_GET[self::PARAM_TYPE] ?? 0;
                    $this->getBots(self::getOffset(), self::getLimit(), $type, self::getBooleanParam(self::PARAM_MULTICHANNEL), self::getIncludeDisabled(), self::getIDs());
                })->name(self::NAME_PREFIX.'bots');
                $this->addEndpoint('/:channelID', function(string $channelID) {
                    $this->getBot($channelID);
                })->name(self::NAME_PREFIX.'bot');
            });
            $this->app->group('/type', function() {
                $this->addEndpoint('/', function() {
                    $this->getTypes(self::getOffset(), self::getLimit(), self::getIncludeDisabled(), self::getIDs());
                })->name(self::NAME_PREFIX.'types');
                $this->addEndpoint('/:type', function(string $type) {
                    $this->getType((int)$type);
                })->name(self::NAME_PREFIX.'type')->conditions([ 'type' => '[1-9][0-9]*' ]);
            });

            $this->app->group('/channel', function() {
                $this->addEndpoint('/:channelID/bots', function(string $channelID) {
                    $this->getBotsForChannel(self::getOffset(), self::getLimit(), $channelID);
                })->name(self::NAME_PREFIX.'channel');
            });
        });
    }

    private static function getOffset(): int
    {
        return (int)$_GET[self::PARAM_OFFSET] ?? 0;
    }

    private static function getLimit(): int
    {
        return isset($_GET[self::PARAM_LIMIT]) ? min((int)$_GET[self::PARAM_LIMIT], self::LIMIT) : self::LIMIT;
    }

    /**
     * @return string[]|null
     */
    private static function getIDs(): ?array
    {
        if(!isset($_GET[self::PARAM_IDS])) {
            return null;
        }
        $ids = explode(',', urldecode($_GET[self::PARAM_IDS]));
        if(count($ids) > self::LIMIT) {
            $ids = array_slice($ids, 0, self::LIMIT);
        }
        return $ids;
    }

    private static function getBooleanParam(string $name): bool
    {
        return isset($_GET[$name]) && !!$_GET[$name];
    }

    private static function getIncludeDisabled(): bool
    {
        return self::getBooleanParam(self::PARAM_DISABLED);
    }

    private static function buildUrlParams(array $params = []): string
    {
        $particles = [];
        foreach($params as $name => $value) {
            $particles[] = $name.'='.urlencode($value);
        }
        return implode('&', $particles);
    }

    private static function formatList(array $list, string $listName, int $total, array $links = []): array
    {
        $offset = self::getOffset();
        $limit = self::getLimit();
        if(array_key_exists(self::LINK_SELF, $links)) {
            $baseUrl = $links[self::LINK_SELF].'&'.self::PARAM_OFFSET.'=';
            $links[self::LINK_PREV] = ($offset > 0 ? $baseUrl.max([ 0, $offset - $limit ]) : null);
            $links[self::LINK_NEXT] = ($offset < $total - $limit && $total > $limit ? $baseUrl.($offset + $limit) : null);
            $links[self::LINK_SELF] = $baseUrl.$offset;
        }
        return [
            'total' => $total,
            $listName => $list,
            self::PROP_LINKS => $links,
        ];
    }

    private function formatBot(Bot $bot): array
    {
        $links = [
            self::LINK_SELF => $this->fullUrlFor('bot', [
                'channelID' => $bot->twitch_id,
            ]),
            self::LINK_WEB => $this->webUrl().'bots/'.$bot->name,
            self::LINK_DOCUMENTATION => $this->webUrl().'api#bot_id',
        ];
        if($bot->type) {
            $links['type'] = $this->fullUrlFor('type', [
                'type' => $bot->type,
            ]);
        }
        if($bot->channel_id) {
            $links['channel'] = $this->fullUrlFor('channel', [
                'channelID' => $bot->channel_id,
            ]);
        }

        return [
            'id' => $bot->twitch_id,
            'username' => $bot->name,
            'type' => $bot->type,
            'channelID' => $bot->channel_id,
            'channelName' => $bot->channel,
            'lastUpdate' => date(\DateTime::W3C, strtotime($bot->date)),
            self::PROP_LINKS => $links,
        ];
    }

    /**
     * @param int $value
     * @return null|bool
     */
    private function intToBool(?int $value = null): ?bool
    {
        if($value === null) {
            return $value;
        }
        return $value > 0;
    }

    private function formatType(Type $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'multiChannel' => $this->intToBool($type->multichannel),
            'lastUpdate' => date(\DateTime::W3C, strtotime($type->date)),
            'description' => $type->description,
            'managed' => $this->intToBool($type->managed),
            'customUsername' => $this->intToBool($type->customUsername),
            'channelsEstimate' => $type->channelsEstimate,
            'businessModel' => $type->payment,
            'hasFreeTier' => $this->intToBool($type->hasFreeTier),
            'apiVersion' => $type->apiVersion,
            'url' => $type->url,
            'sourceCodeURL' => $type->sourceUrl,
            'commandsURL' => $type->commandsUrl,
            'active' => $this->intToBool($type->enabled),
            self::PROP_LINKS => [
                self::LINK_SELF => $this->fullUrlFor('type', [
                    'type' => $type->id
                ]),
                'bots' => $this->fullUrlFor('bots').'?'.self::PARAM_TYPE.'='.$type->id,
                self::LINK_WEB => $this->webUrl().'types/'.$type->id,
                self::LINK_DOCUMENTATION => $this->webUrl().'api#type_id',
            ],
        ];
    }

    private static function boolToParam(bool $value): string
    {
        return $value ? '1' : '0';
    }

    private function sendResponse(): void
    {
        if(array_key_exists(self::PROP_LINKS, $this->result)) {
            $linkHeader = [];
            foreach($this->result[self::PROP_LINKS] as $rel => $link) {
                $linkHeader[] = '<'.$link.'>;rel='.$rel;
            }
            $this->app->response->headers->set('Link', implode(', ', $linkHeader));
        }
        echo json_encode($this->result);
        $this->app->stop();
    }

    private function addEndpoint(string $path, callable $cbk): \Slim\Route
    {
        $boundSendResponse = function(...$args) use($cbk) {
            $cbk(...$args);
            $this->sendResponse();
        };
        \Closure::bind($boundSendResponse, $this);
        return $this->app->get($path, $boundSendResponse);
    }

    private function sendError(int $code, string $error): void
    {
        $this->app->halt($code, json_encode([
            'error' => $error,
            'code' => $code
        ]));
    }

    private function checkPagination(): void
    {
        if(isset($_GET[self::PARAM_LIMIT]) &&
           (!is_numeric($_GET[self::PARAM_LIMIT]) || (int)$_GET[self::PARAM_LIMIT] <= 0))
            $this->app->halt(400, $this->sendError(400, 'Invalid limit specified'));
        if(isset($_GET[self::PARAM_OFFSET]) &&
           (!is_numeric($_GET[self::PARAM_OFFSET]) || (int)$_GET[self::PARAM_OFFSET] < 0))
            $this->app->halt(400, $this->sendError(400, 'Invalid offset specified'));
    }

    private function fullUrlFor(string $name, array $params = []): string
    {
        return $this->app->request->getUrl().$this->app->urlFor(self::NAME_PREFIX.$name, $params);
    }

    private function webUrl(): string
    {
        return $this->app->config('webUrl');
    }

    private function lastModified(int $lastModified): void
    {
        $this->app->lastModified(max([ self::LAST_MODIFIED, $lastModified ]));
    }

    public function getBot(string $id): void
    {
        $bot = $this->model->bots->getBotByID($id);
        if(!$bot) {
            $this->app->notFound();
        }
        $this->lastModified(strtotime($bot->date));
        $this->result = $this->formatBot($bot);
    }

    public function getBotsForChannel(
        int $offset = 0,
        int $limit = self::LIMIT,
        string $channelID
    ): void
    {
        $descriptor = new BotListDescriptor();

        $descriptor->channelID = $channelID;
        $this->lastModified($this->model->bots->getLastListUpdate($descriptor));
        $total = $this->model->bots->getCount($descriptor);

        $descriptor->reset();
        $descriptor->offset = $offset;
        $descriptor->limit = $limit;
        $bots = $this->model->bots->list($descriptor);

        $this->result = self::formatList(array_map([$this, 'formatBot'], $bots), 'bots', $total, [
            self::LINK_SELF => $this->fullUrlFor('channel', [
                'channelID' => $channelID
            ]).'?'.self::PARAM_LIMIT.'='.$limit,
            self::LINK_DOCUMENTATION => $this->webUrl().'api#channelid_bots',
        ]);
    }

    public function getBots(
        int $offset = 0,
        int $limit = self::LIMIT,
        int $type = 0,
        bool $multichannel = false,
        bool $includeDisabled = false,
        ?array $ids = null
    ): void
    {
        $descriptor = new BotListDescriptor();

        $descriptor->type = $type;
        $descriptor->multichannel = $multichannel;
        $descriptor->includeDisabled = $includeDisabled;
        $descriptor->ids = $ids;

        $this->lastModified($this->model->bots->getLastListUpdate($descriptor));
        $total = $this->model->bots->getCount($descriptor);

        $descriptor->reset();
        $descriptor->offset = $offset;
        $descriptor->limit = $limit;

        $bots = $this->model->bots->list($descriptor);

        $params = [
            self::PARAM_LIMIT => $limit,
            self::PARAM_MULTICHANNEL => self::boolToParam($multichannel),
            self::PARAM_DISABLED => self::boolToParam($includeDisabled),
        ];
        if($ids != null) {
            $params[self::PARAM_IDS] = implode(',', $ids);
        }
        if($type != 0) {
            $params[self::PARAM_TYPE] = $type;
        }

        $this->result = self::formatList(array_map([$this, 'formatBot'], $bots), 'bots', $total, [
            self::LINK_SELF => $this->fullUrlFor('bots').'?'.self::buildUrlParams($params),
            self::LINK_WEB => $this->webUrl().'bots'.($type != 0 ? '?type='.$type : ''),
            self::LINK_DOCUMENTATION => $this->webUrl().'api#bot',
        ]);
    }

    public function getType(int $id): void
    {
        $type = $this->model->types->getType($id);
        if(!$type) {
            $this->app->notFound();
        }
        $this->lastModified(strtotime($type->date));
        $this->result = $this->formatType($type);
    }

    public function getTypes(
        int $offset = 0,
        int $limit = self::LIMIT,
        bool $includeDisabled,
        ?array $ids = null
    ): void
    {
        $descriptor = new TypeListDescriptor();
        $descriptor->includeDisabled = $includeDisabled;
        $descriptor->ids = $ids;
        $this->lastModified($this->model->types->getLastListUpdate($descriptor));
        $total = $this->model->types->getCount($descriptor);

        // Limit result set to actually get results.
        $descriptor->reset();
        $descriptor->limit = $limit;
        $descriptor->offset = $offset;
        $types = $this->model->types->list($descriptor);

        $params = [
            self::PARAM_LIMIT => $limit,
            self::PARAM_DISABLED => self::boolToParam($includeDisabled),
        ];
        if($ids !== null) {
            $params[self::PARAM_IDS] = implode(',', $ids);
        }

        $this->result = self::formatList(array_map([$this, 'formatType'], $types), 'types', $total, [
            self::LINK_SELF => $this->fullUrlFor('types').'?'.self::buildUrlParams($params),
            self::LINK_WEB => $this->webUrl().'types?disabled='.self::boolToParam($includeDisabled),
            self::LINK_DOCUMENTATION => $this->webUrl().'api#type',
        ]);
    }
}
