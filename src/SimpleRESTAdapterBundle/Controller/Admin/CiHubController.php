<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller\Admin;

use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use Pimcore\Bundle\AdminBundle\Controller\AdminAbstractController;
use Pimcore\Controller\FrontendController;
use Pimcore\Db;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/ci-hub")
 */
class CiHubController extends AdminAbstractController
{
    /**
     * @param Request $request
     * @return Response
     * @Route("/config/list", name="admin_ci_hub_user_config_list", options={"expose":true})
     * @throws Exception
     */
    public function list(DataHubConfigurationRepository $configRepository): Response {
        $list = $configRepository->all();
        return new JsonResponse([
            'data' => array_keys($list),
            'success' => count($list) > 0
        ]);
    }
    /**
     * @param Request $request
     * @return Response
     * @Route("/config/update", name="admin_ci_hub_user_config_update", options={"expose":true})
     * @throws Exception
     */
    public function update(Request $request): Response {
        /** @var User|User\Role|null $user */
        $user = User\UserRole::getById($request->request->getInt('id'));
        $currentUserIsAdmin = $this->getAdminUser()->isAdmin();

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if ($user instanceof User && $user->isAdmin() && !$currentUserIsAdmin) {
            throw $this->createAccessDeniedHttpException('Only admin users are allowed to modify admin users');
        }

        if ($request->get('data')) {
            $db = Db::get();

            $data = $db->fetchOne('SELECT data FROM users_datahub_config WHERE userId = ' . $user->getId());
            if($data) {
                $db->update('users_datahub_config', [
                    'data' => $request->get('data')
                ], ['userId' => $user->getId()]);
            } else {
                $db->insert('users_datahub_config', [
                    'data' => $request->get('data'),
                    'userId' => $user->getId()
                ]);
            }
        }

        return $this->adminJson(['success' => true]);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     * @Route("/config", name="admin_ci_hub_user_config", options={"expose":true})
     */
    public function get(Request $request): Response {
        $userId = (int)$request->get('id');
        if ($userId < 1) {
            throw $this->createNotFoundException();
        }

        $user = User::getById($userId);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if ($user->isAdmin() && !$this->getAdminUser()->isAdmin()) {
            throw $this->createAccessDeniedHttpException('Only admin users are allowed to modify admin users');
        }

        $db = Db::get();
        $data = $db->fetchOne('SELECT data FROM users_datahub_config WHERE userId = ' . $user->getId());

        if(!$data) {
            $data = '{}';
        }

        return new JsonResponse(json_decode($data));
    }
}
