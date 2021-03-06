<?php

namespace Ext\DirectBundle\Response;

use Symfony\Component\Form\Form;

/**
 * @author Semyon Velichko <semyon@velichko.net>
 */
class Error extends Response implements ResponseInterface
{
    
    protected $success = false;
    
    public function formatResponse(array $data)
    {
        $config = $this->factory->getResolver()->getMethodConfig();
        $msg = $this->factory
            ->getContainer()
            ->get('ext_direct.controller')
            ->render($this->config['error_template'], array('errors' => $data))
            ->getContent();
        return array($config['reader']['successProperty'] => $this->success, 'msg' => $msg);
    }
    
}
