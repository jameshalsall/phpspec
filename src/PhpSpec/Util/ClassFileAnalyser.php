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

namespace PhpSpec\Util;

use PhpSpec\Exception\Generator\NamedMethodNotFoundException;
use PhpSpec\Exception\Generator\NoMethodFoundInClass;

final class ClassFileAnalyser
{
    private $tokenLists = array();

    /**
     * @param string $class
     * @return int
     */
    public function getStartLineOfFirstMethod(string $class): int
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->offsetForDocblock($tokens, $this->findIndexOfFirstMethod($tokens));
        return $tokens[$index][2];
    }

    /**
     * @param string $class
     * @return int
     */
    public function getEndLineOfLastMethod(string $class): int
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->findEndOfLastMethod($tokens, $this->findIndexOfClassEnd($tokens));
        return $tokens[$index][2];
    }

    /**
     * @param string $class
     * @return bool
     */
    public function classHasMethods(string $class): bool
    {
        foreach ($this->getTokensForClass($class) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $class
     * @param string $methodName
     * @return int
     */
    public function getEndLineOfNamedMethod(string $class, string $methodName): int
    {
        $tokens = $this->getTokensForClass($class);

        $index = $this->findIndexOfNamedMethodEnd($tokens, $methodName);
        return $tokens[$index][2];
    }

    /**
     * @param string $class
     *
     * @return int
     */
    public function getLastLineOfClassDeclaration($class)
    {
        $tokens = $this->getTokensForClass($class);

        if (null === $index = $this->findEndOfImplementsDeclaration($tokens)) {
            $index = $this->findIndexOfClassDeclaration($tokens);
        }

        return $tokens[$index][2];
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function classImplementsAnyInterface(string $class): bool
    {
        $tokens = $this->getTokensForClass($class);

        foreach ($tokens as $token) {
            if (is_array($token) && T_IMPLEMENTS === $token[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    public function getClassNamespace($class)
    {
        $tokens = $this->getTokensForClass($class);

        $namespaceParts = [];
        while ($token = next($tokens)) {
            if (is_array($token) && T_NAMESPACE === $token[0]) {
                $namespaceParts = $this->extractNamespacePartsFromNamespaceLine($namespaceParts, $tokens);
            }
        }

        return implode('\\', $namespaceParts);
    }

    /**
     * @param string $class
     *
     * @return int
     */
    public function getLastLineOfUseStatements($class)
    {
        $tokens = $this->getTokensForClass($class);

        $lastUseStatementLine = null;
        while ($token = next($tokens)) {
            if (!is_array($token)) {
                continue;
            }

            if (T_USE === $token[0]) {
                $lastUseStatementLine = $token[2];

                continue;
            }

            if (T_CLASS === $token[0]) {
                return $lastUseStatementLine;
            }
        }

        return $lastUseStatementLine;
    }

    /**
     * @param string $class
     *
     * @return int
     */
    public function getLineOfNamespaceDeclaration($class)
    {
        $tokens = $this->getTokensForClass($class);

        while ($token = next($tokens)) {
            if (is_array($token) && T_NAMESPACE === $token[0]) {
                return $token[2];
            }
        }

        return null;
    }

    public function findEndOfLastMethod(array $tokens, int $index): int
    {
        for ($i = $index - 1; $i > 0; $i--) {
            if ($tokens[$i] == "}") {
                return $i + 1;
            }
        }
        throw new NoMethodFoundInClass();
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfFirstMethod(array $tokens): int
    {
        for ($i = 0, $max = \count($tokens); $i < $max; $i++) {
            if ($this->tokenIsFunction($tokens[$i])) {
                return $i;
            }
        }
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return int
     */
    private function offsetForDocblock(array $tokens, int $index): int
    {
        $allowedTokens = array(
            T_FINAL,
            T_ABSTRACT,
            T_PUBLIC,
            T_PRIVATE,
            T_PROTECTED,
            T_STATIC,
            T_WHITESPACE
        );

        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                return $index;
            }

            if (\in_array($token[0], $allowedTokens)) {
                continue;
            }

            if ($token[0] === T_DOC_COMMENT) {
                return $i;
            }

            return $index;
        }
    }

    /**
     * @param $class
     * @return array
     */
    private function getTokensForClass($class): array
    {
        $hash = md5($class);

        if (!\in_array($hash, $this->tokenLists)) {
            $this->tokenLists[$hash] = token_get_all($class);
        }

        return $this->tokenLists[$hash];
    }

    /**
     * @param array $tokens
     * @param string $methodName
     * @return int
     */
    private function findIndexOfNamedMethodEnd(array $tokens, string $methodName): int
    {
        $index = $this->findIndexOfNamedMethod($tokens, $methodName);
        return $this->findIndexOfMethodOrClassEnd($tokens, $index);
    }

    /**
     * @param array $tokens
     * @param string $methodName
     * @return int
     * @throws NamedMethodNotFoundException
     */
    private function findIndexOfNamedMethod(array $tokens, string $methodName): int
    {
        $searching = false;

        for ($i = 0, $max = \count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $searching = true;
            }

            if (!$searching) {
                continue;
            }

            if ($token[0] === T_STRING) {
                if ($token[1] === $methodName) {
                    return $i;
                }

                $searching = false;
            }
        }

        throw new NamedMethodNotFoundException('Target method not found');
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return int
     */
    private function findIndexOfMethodOrClassEnd(array $tokens, int $index): int
    {
        $braceCount = 0;

        for ($i = $index, $max = \count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if ('{' === $token || $this->isSpecialBraceToken($token)) {
                $braceCount++;
                continue;
            }

            if ('}' === $token) {
                $braceCount--;
                if ($braceCount === 0) {
                    return $i + 1;
                }
            }
        }
    }

    private function isSpecialBraceToken($token)
    {
        if (!\is_array($token)) {
            return false;
        }

        return $token[1] === "{";
    }

    /**
     * @param mixed $token
     * @return bool
     */
    private function tokenIsFunction($token): bool
    {
        return \is_array($token) && $token[0] === T_FUNCTION;
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfClassEnd(array $tokens): int
    {
        $classTokens = $this->filterTokensForClassTokens($tokens);
        $classTokenIndex = key($classTokens);

        return $this->findIndexOfMethodOrClassEnd($tokens, $classTokenIndex) - 1;
    }

    private function findIndexOfClassDeclaration(array $tokens): int
    {
        $classTokens = $this->filterTokensForClassTokens($tokens);

        return key($classTokens);
    }

    private function findEndOfImplementsDeclaration(array $tokens)
    {
        $i = 0;
        while ($token = next($tokens)) {
            if (is_array($token) && T_IMPLEMENTS === $token[0]) {
                while ($token = next($tokens)) {
                    if (!is_array($token) && '{' === $token) {
                        return $i + 1;
                    }

                    $i++;
                }
            }

            $i++;
        }

        return null;
    }

    private function filterTokensForClassTokens(array $tokens): array
    {
        $classTokens = array_filter($tokens, function ($token) {
            return \is_array($token) && $token[0] === T_CLASS;
        });

        return $classTokens;
    }

    private function extractNamespacePartsFromNamespaceLine(array $namespaceParts, array $tokens): array
    {
        $currentToken = current($tokens);
        $currentLineNumber = $currentToken[2];

        while ($namespaceToken = next($tokens)) {
            if (!is_array($namespaceToken)) {
                continue;
            }

            if ($currentLineNumber !== $namespaceToken[2]) {
                break;
            }

            if (T_STRING === $namespaceToken[0]) {
                $namespaceParts[] = $namespaceToken[1];
            }
        }

        return $namespaceParts;
    }
}
