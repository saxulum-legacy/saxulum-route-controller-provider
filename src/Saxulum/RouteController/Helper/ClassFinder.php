<?php

namespace Saxulum\RouteController\Helper;

class ClassFinder
{
    /**
     * @param  string $phpCode
     * @return array
     */
    public static function findClasses($phpCode)
    {
        $tokens = token_get_all($phpCode);
        $count = count($tokens);

        $namespaceStack = array();
        $namespaceCurlies = array();

        $classes = array();

        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] == T_NAMESPACE) {
                $i += 2;
                $namespace = '';
                do {
                    $namespace .= $tokens[$i++][1];
                } while (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_NS_SEPARATOR, T_STRING)));

                array_push($namespaceStack, $namespace);

                if (is_string($tokens[$i]) && $tokens[$i] == '{') {
                    $namespaceCurlies[$namespace] = 0;
                } elseif (is_string($tokens[$i + 1]) && $tokens[$i + 1] == '{') {
                    $namespaceCurlies[$namespace] = 0;
                    $i++;
                }
            }

            $activeNamespace = end($namespaceStack);

            if (isset($namespaceCurlies[$activeNamespace])) {
                if (is_string($tokens[$i]) && $tokens[$i] == '{') {
                    $namespaceCurlies[$activeNamespace]++;
                } elseif (is_string($tokens[$i]) && $tokens[$i] == '}') {
                    $namespaceCurlies[$activeNamespace]--;
                    if ($namespaceCurlies[$activeNamespace] == 0) {
                        array_pop($namespaceStack);
                    }
                }
            }

            if (is_array($tokens[$i]) && $tokens[$i][0] == T_CLASS) {
                $className = $tokens[$i + 2][1];
                $classes[] = implode('\\', $namespaceStack) . '\\'. $className;
            }
        }

        return $classes;
    }
}
