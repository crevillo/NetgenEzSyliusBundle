<?php

class eZSyliusUser extends eZUser
{
    /**
     * Checks if there's an admin sylius user having login entered by the user.
     * If so, it checks password entered is correct and if that is the case,
     * it returns the legacy user.
     *
     * @todo deal with the case where the name/email can be in sylius database
     *    but is not connected to any ez user.
     */
    public static function loginUser($login, $password, $authenticationMatch = false)
    {
        $container = ezpKernel::instance()->getServiceContainer();
        try {
            /** @var \Netgen\Bundle\EzSyliusBundle\Entity\AdminUser $syliusUser */
            $syliusUser = $container->get('netgen_ez_sylius.admin_user.provider.email_or_name_based')
                ->loadUserByUsername($login);

            $factory = $container->get('security.encoder_factory');
            $encoder = $factory->getEncoder($syliusUser);

            // check if login and password are legit.
            $check = $encoder->isPasswordValid(
                $syliusUser->getPassword(),
                $password,
                $syliusUser->getSalt()
            );

            if (!$check) {
                return false;
            }

            return self::fetch($syliusUser->getAPIUser()->getUserId());

        } catch(Exception $exception) {
            return false;
        }
    }
}