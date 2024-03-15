<?php

/**
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
 * @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 */

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Manager;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AccessDeniedException;
use Doctrine\DBAL\Exception;
use Pimcore\Db;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final readonly class AuthManager
{
    private Request $request;

    public function __construct(
        private RequestStack $requestStack
    ) {
        $this->request = $this->requestStack->getMainRequest();
    }

    /**
     * @throws Exception
     */
    public function checkAuthentication(): void
    {
        $user = $this->getUserByToken();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @throws Exception
     */
    public function authenticate(): User
    {
        $this->checkAuthentication();
        $user = $this->getUserByToken();
        if (self::isValidUser($user)) {
            return $user;
        }

        throw new AuthenticationException('Failed to authenticate with username and token');
    }

    /**
     * @throws Exception
     */
    private function getUserByToken(): ?User
    {
        if (!$this->request->headers->has('Authorization')
            || !str_starts_with((string) $this->request->headers->get('Authorization'), 'Bearer ')) {
            throw new AccessDeniedException();
        }

        // skip beyond "Bearer "
        $authorizationHeader = mb_substr((string) $this->request->headers->get('Authorization'), 7);

        $connection = Db::get();
        $userId = $connection->fetchOne('SELECT userId FROM users_datahub_config WHERE JSON_UNQUOTE(JSON_EXTRACT(data, \'$.apikey\')) = ?', [$authorizationHeader]);
        $user = User::getById($userId);
        if ($user instanceof User\AbstractUser) {
            return $user;
        }

        throw new AuthenticationException('Failed to authenticate with username and token');
    }

    private function isValidUser(?User $user): bool
    {
        return $user instanceof User && $user->isActive() && $user->getId();
    }
}
