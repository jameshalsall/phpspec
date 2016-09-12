<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Example\TypeFailureException;
use PhpSpec\Formatter\Presenter\Presenter;

final class TypeMatcher extends BasicMatcher
{
    /**
     * @var array
     */
    private static $keywords = array(
        'beAnInstanceOf',
        'returnAnInstanceOf',
        'haveType',
        'implement'
    );
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @param Presenter $presenter
     */
    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return bool
     */
    public function supports($name, $subject, array $arguments)
    {
        return in_array($name, self::$keywords)
            && 1 == count($arguments)
        ;
    }

    /**
     * @param mixed $subject
     * @param array $arguments
     *
     * @return bool
     */
    protected function matches($subject, array $arguments)
    {
        return (null !== $subject) && ($subject instanceof $arguments[0]);
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return TypeFailureException
     */
    protected function getFailureException($name, $subject, array $arguments)
    {
        return new TypeFailureException(sprintf(
            'Expected an instance of %s, but got %s.',
            $this->presenter->presentString($arguments[0]),
            $this->presenter->presentValue($subject)
        ), $subject, $arguments[0]);
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return FailureException
     */
    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Did not expect instance of %s, but got %s.',
            $this->presenter->presentString($arguments[0]),
            $this->presenter->presentValue($subject)
        ));
    }
}
