<?php

namespace Simp\Commerce\commerce_panel;

use Simp\Commerce\account\User;
use Simp\Router\middleware\access\Access;
use Simp\Router\middleware\interface\Middleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AccessMiddleware implements Middleware
{
    public function __invoke(Request $request, Access $access_interface, $next)
    {
        $route = $access_interface->options['options'] ?? null;

        // If no route info, allow access
        if (!$route || empty($route['controller_method'])) {
            $access_interface->access_granted = true;
            return $next($request, $access_interface);
        }

        $route_name = $route['controller_method'];

        // Public routes
        $publicOnce = ['createUserAccount'];
        $publicAnonymous = ['loginAccount'];

        /* ---------------------------------------------------------
         * PUBLIC-ONCE ROUTE (createUserAccount)
         * Only accessible if:
         * - No users exist OR
         * - User is logged in
         * --------------------------------------------------------- */
        if (in_array($route_name, $publicOnce)) {

            $hasUser = User::count();        // Replace with count() or isEmpty() if you want
            $loggedIn = User::currentUser()?->isLogin();

            if (empty($hasUser) || $loggedIn) {
                $access_interface->access_granted = true;
            } else {
                $access_interface->access_granted = false;
                $access_interface->response = new RedirectResponse('/');
            }

            return $next($request, $access_interface);
        }

        /* ---------------------------------------------------------
         * PUBLIC-ANONYMOUS ROUTE (loginAccount)
         * Should be accessible ONLY when NOT logged in
         * --------------------------------------------------------- */
        if (in_array($route_name, $publicAnonymous)) {

            $loggedIn = User::currentUser()?->isLogin();

            if ($loggedIn) {
                // Already logged in → block login route
                $access_interface->access_granted = false;
                $access_interface->response = new RedirectResponse('/');
            } else {
                // Not logged in → allow login
                $access_interface->access_granted = true;
                $access_interface->response = new RedirectResponse('/user/login');
            }

            return $next($request, $access_interface);
        }

        /* ---------------------------------------------------------
         * ALL OTHER ROUTES REQUIRE LOGIN
         * --------------------------------------------------------- */
        $access_interface->access_granted = User::currentUser()?->isLogin() ?? false;
        if (!$access_interface->access_granted) {
            $access_interface->response = new RedirectResponse('/user/login');
        }

        return $next($request, $access_interface);
    }
}
