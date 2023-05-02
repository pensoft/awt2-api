<?php


namespace App\Guards;


use App\Exceptions\TokenPermissionsMismatchException;
use GuzzleHttp\Client;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ApiTokenGuard implements Guard
{
    use GuardHelpers;

    private $storageKey = '';
    private $request;
    private $inputKey = '1';


    public function __construct(UserProvider $provider, Request $request, $configuration)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    public function user(): ?Authenticatable
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $user = null;
        $token = $this->getTokenForRequest();

        // retrieve via token
        if (!empty($token)) {
            $data = Cache::remember($token, Config::get('cache.token_data_ttl'), function () use ($token) {
                // the token was found, how you want to pass?
                try {
                    return $this->provider->retrieveByToken(null, $token);
                } catch (\Exception $exception){
                    logger('Request to API was declined', [$exception->getMessage()]);
                    throw new AuthenticationException();
                }
            });

            if ($data && $this->checkPolicies()['allow'] == 'true') {
                $user = $data;
            } else {
                logger('Request to API was declined');
                throw new TokenPermissionsMismatchException("You don't have permissions!");
            }
        } else {
            throw new AuthenticationException();
        }

        return $this->user = $user;
    }

    public function getTokenForRequest()
    {
        return $this->request->bearerToken();
    }

    private function checkPolicies()
    {
        $info = $this->getRequestInfo();
        $key = hash('sha256', implode('|', Arr::only($info, ['method', 'url', 'token', 'format'])));

        return  Cache::remember($key, Config::get('cache.token_data_ttl'), function () {
            $token = $this->getTokenForRequest();

            try {
                $response = (new Client())->post(
                    config('auth.identity_provider.server') . '/api/v1/authorization/check',
                    $this->getRequestOptions($token)
                );
                return json_decode($response->getBody(), true);
            } catch (\Exception $exception) {
                try {
                    $response = (new Client())->post(
                        config('auth.identity_provider.server') . '/api/v1/services/authorization/check',
                        $this->getRequestOptions($token)
                    );

                    return json_decode($response->getBody(), true);
                } catch (\Exception $exception) {
                    return ['allow' => 'false'];
                }
            }
        });
    }

    public function validate(array $credentials = []): bool
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        $credentials = [$this->storageKey => $credentials[$this->inputKey]];

        if ($this->provider->retrieveByCredentials($credentials)) {
            return true;
        }

        return false;
    }

    protected function getRequestOptions($token)
    {
        $pathInfo = str_replace('/api', '', $this->request->getPathInfo());
        return [
            'headers'     => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'form_params' => [
                'uri'    => $pathInfo,
                'method' => $this->request->getMethod(),
            ],
            'verify' => env('VERIFY_SSL', true)
        ];
    }

    private function getRequestInfo(){
        return [
            'type' => ($this->request->isJson() ? 'json' :
                (strpos($this->request->header('Content-Type'),'multipart') !== false ? 'multipart' :
                    ($this->request->header('Content-Type') == 'application/x-www-form-urlencoded' ? 'form' : $this->request->header('Content-Type')))),
            'agent' => $this->request->userAgent(),
            'method' => $this->request->method(),
            'token' => $this->request->bearerToken(),
            'full_url'=>$this->request->fullUrl(),
            'url'=>$this->request->url(),
            'format'=>$this->request->format(),
            'query' =>$this->request->query(),
            'params' => $this->request->all(),
        ];
    }
}
