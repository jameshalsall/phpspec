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

namespace PhpSpec\CodeGenerator\Writer;

use PhpSpec\Util\ClassFileAnalyser;

final class TokenizedCodeWriter implements CodeWriter
{
    /**
     * @var ClassFileAnalyser
     */
    private $analyser;

    /**
     * @param ClassFileAnalyser $analyser
     */
    public function __construct(ClassFileAnalyser $analyser)
    {
        $this->analyser = $analyser;
    }

    public function insertMethodFirstInClass(string $class, string $method) : string
    {
        if (!$this->analyser->classHasMethods($class)) {
            return $this->writeAtEndOfClass($class, $method);
        }

        $line = $this->analyser->getStartLineOfFirstMethod($class);

        return $this->insertStringBeforeLine($class, $method, $line);
    }

    public function insertMethodLastInClass(string $class, string $method) : string
    {
        if ($this->analyser->classHasMethods($class)) {
            $line = $this->analyser->getEndLineOfLastMethod($class);
            return $this->insertStringAfterLine($class, $method, $line);
        }

        return $this->writeAtEndOfClass($class, $method);
    }

    public function insertAfterMethod(string $class, string $methodName, string $method) : string
    {
        $line = $this->analyser->getEndLineOfNamedMethod($class, $methodName);

        return $this->insertStringAfterLine($class, $method, $line);
    }

    public function insertImplementsInClass(string $class, string $interface) : string
    {
        $classLines = explode("\n", $class);
        $interfaceNamespace = $this->extractNamespaceFromFQN($interface);

        $classNamespace = $this->analyser->getClassNamespace($class);
        $lastLineOfClassDeclaration = $this->analyser->getLastLineOfClassDeclaration($class);

        if ($classNamespace === $interfaceNamespace) {
            $interfaceName = ltrim(str_replace($classNamespace, '', $interface), '\\');
        } else {
            $interfaceName = $this->extractShortNameFromFQN($interface);
            $useStatement = sprintf('use %s;', $interface);

            $lastLineOfUseStatements = $this->analyser->getLastLineOfUseStatements($class);
            if (null !== $lastLineOfUseStatements) {
                array_splice($classLines, $lastLineOfUseStatements, 0, [$useStatement]);
                $lastLineOfClassDeclaration++;
            } else {
                $lineOfNamespaceDeclaration = $this->analyser->getLineOfNamespaceDeclaration($class);
                array_splice($classLines, $lineOfNamespaceDeclaration, 0, ['', $useStatement]);
                $lastLineOfClassDeclaration += 2;
            }
        }

        $lastClassDeclarationLine = $classLines[$lastLineOfClassDeclaration - 1];
        $newLineModifier = (false === strpos($lastClassDeclarationLine, 'class ')) ? "\n" . '    ' : ' ';

        if ($this->analyser->classImplementsAnyInterface($class)) {
            $lastClassDeclarationLine .= ',' . $newLineModifier . $interfaceName;
        } else {
            $lastClassDeclarationLine .= ' implements ' . $interfaceName;
        }

        $classLines[$lastLineOfClassDeclaration - 1] = $lastClassDeclarationLine;

        return implode("\n", $classLines);
    }

    private function insertStringAfterLine(string $target, string $toInsert, int $line, bool $leadingNewline = true) : string
    {
        $lines = explode("\n", $target);
        $lastLines = \array_slice($lines, $line);
        $toInsert = trim($toInsert, "\n\r");
        if ($leadingNewline) {
            $toInsert = "\n" . $toInsert;
        }
        array_unshift($lastLines, $toInsert);
        array_splice($lines, $line, \count($lines), $lastLines);

        return implode("\n", $lines);
    }

    private function insertStringBeforeLine(string $target, string $toInsert, int $line) : string
    {
        $line--;
        $lines = explode("\n", $target);
        $lastLines = \array_slice($lines, $line);
        array_unshift($lastLines, trim($toInsert, "\n\r") . "\n");
        array_splice($lines, $line, \count($lines), $lastLines);

        return implode("\n", $lines);
    }

    private function writeAtEndOfClass(string $class, string $method, bool $prependNewLine = false) : string
    {
        $tokens = token_get_all($class);
        $searching = false;
        $inString = false;
        $searchPattern = array();

        for ($i = \count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if ($token === '}' && !$inString) {
                $searching = true;
                continue;
            }

            if (!$searching) {
                continue;
            }

            if ($token === '"') {
                $inString = !$inString;
                continue;
            }

            if ($this->isWritePoint($token)) {
                $line = $token[2];
                return $this->insertStringAfterLine($class, $method, $line, $token[0] === T_COMMENT ?: $prependNewLine);
            }

            array_unshift($searchPattern, \is_array($token) ? $token[1] : $token);

            if ($token === '{') {
                $search = implode('', $searchPattern);
                $position = strpos($class, $search) + \strlen($search) - 1;

                return substr_replace($class, "\n" . $method . "\n", $position, 0);
            }
        }
    }

    /**
     * @param $token
     */
    private function isWritePoint($token) : bool
    {
        return \is_array($token) && ($token[1] === "\n" || $token[0] === T_COMMENT);
    }

    private function extractShortNameFromFQN(string $fullyQualifiedName): string
    {
        preg_match('/\\\(\w+)$/', $fullyQualifiedName, $matches);

        return $matches[1];
    }

    private function extractNamespaceFromFQN(string $fullyQualifiedName):string
    {
        if (false === strpos($fullyQualifiedName, '\\')) {
            return '';
        }

        $parts = explode('\\', $fullyQualifiedName);
        array_pop($parts);

        return join('\\', $parts);
    }
}
