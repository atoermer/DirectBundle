<?php
namespace Ext\DirectBundle\Router;


use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver as BaseControllerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpFoundation\Request as HttpFoundation_Request;

/**
 * @author Semyon Velichko <semyon@velichko.net>
 */
class ControllerResolver extends BaseControllerResolver {
    
    private $call;
    private $bundle;
    private $controller;
    private $config;
    private $methodConfigKey;
    
    public function __construct(ContainerInterface $container, ControllerNameParser $parser, LoggerInterface $logger = null)
    {
        $this->kernel = $container->get('kernel');
        parent::__construct($container, $parser, $logger);
    }
    
    public function setCall(Call $call)
    {
        $this->call = $call;
        return $this;
    }
    
    public function getCall()
    {
        return $this->call;
    }
    
    public function setBundle(Bundle $bundle)
    {
        $this->bundle = $bundle;
        return $this;
    }
    
    public function getBundle()
    {
        return $this->bundle;
    }
    
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }
    
    public function getConfig()
    {
        return $this->config;
    }
    
    public function setMethodConfigKey($key)
    {
        $this->methodConfigKey = $key;
    }
    
    public function getMethodConfigKey()
    {
        return $this->methodConfigKey;
    }
    
    public function getMethodConfig()
    {
        if($this->getMethodConfigKey())
            return $this->config['router']['rules'][$this->getMethodConfigKey()];
    }
    
    public function getControllerFromCall(Call $call)
    {
        $this->setCall($call);
        
        list($bundle, $controller) = explode('_', $call->getAction());
        $fullPath = sprintf('%1$sBundle:%2$s:%3$s', $bundle, $controller, $call->getMethod());
        
        foreach($this->config['router']['rules'] as $key => $rule)
        {
            if(isset($rule['defaults']) && isset($rule['defaults']['_controller']) && $rule['defaults']['_controller'] === $fullPath)
                $this->setMethodConfigKey($key);
        }
        
        if(!$this->getMethodConfigKey())
            throw new \BadMethodCallException(sprintf('%1$s does not configured, check config.yml', $fullPath));
        
        $bundle = $this->kernel->getBundle(sprintf('%sBundle', $bundle));
        $this->setBundle($bundle);
        
        $controller = sprintf('%s\\Controller\\%sController::%sAction', $bundle->getNamespace(), $controller, $call->getMethod());

        if (is_array($controller) || (is_object($controller) && method_exists($controller, '__invoke'))) {
            return $controller;
        }

        if (false === strpos($controller, ':') && method_exists($controller, '__invoke')) {
            return new $controller;
        }

        list($controller, $method) = $this->createController($controller);

        if (!method_exists($controller, $method)) {
            throw new \InvalidArgumentException(sprintf('Method "%s::%s" does not exist.', get_class($controller), $method));
        }

        return array($controller, $method);
    }
    
   protected function doGetArguments(HttpFoundation_Request $request, $controller, array $parameters)
    {
        if(!$this->call) {
            throw new \LogicException('$this->call is null, run setCall(Call $call) or getControllerFromCall(Call $call) before use getArguments()');
        }
        
        $attributes = $this->call->getData();
        $arguments = array();
        foreach ($parameters as $param) {
            if(in_array($param->getName(), array('_data', '_list'))) {
                $arguments[] = $attributes;
                if('_list' === $param->getName() && !isset($attributes[0])) {
                    array_pop($arguments);
                    $arguments[] = array($attributes);
                }
            } elseif (array_key_exists($param->getName(), $attributes)) {
                $arguments[] = $attributes[$param->getName()];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->getName()));
            }
        }

        return $arguments;
    }
    
}