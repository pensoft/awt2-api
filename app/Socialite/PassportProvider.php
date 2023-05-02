<?php

namespace App\Socialite;

use Illuminate\Auth\AuthenticationException;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use Illuminate\Support\Arr;

class PassportProvider extends AbstractProvider implements ProviderInterface
{

    protected $usesPKCE = true;
    protected $stateless = true;

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(config('auth.identity_provider.server') . 'oauth/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return config('auth.identity_provider.server') . 'oauth/token';
    }

    /**
     * {@inheritdoc}
     */
    public function getUserByToken($token)
    {
        try {
            $response = $this->getHttpClient()->get(
                config('auth.identity_provider.server') . '/api/me',
                $this->getRequestOptions($token)
            );
        } catch (\Exception $exception) {
            try {
                $response = $this->getHttpClient()->get(
                    config('auth.identity_provider.server') . '/api/services/me',
                    $this->getRequestOptions($token)
                );
            } catch (\Exception $exception) {
                throw new AuthenticationException();
            }
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $user = $user['data'];

        return (new User)->setRaw($user)->map([
            'id'    => $user['id'],
            'name'  => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'role' => Arr::get($user, 'role'),
        ]);
    }

    /**
     * Get the default options for an HTTP request.
     *
     * @param string $token
     * @return array
     */
    protected function getRequestOptions($token)
    {
        return [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'verify' => env('VERIFY_SSL', true)
        ];
    }
}
