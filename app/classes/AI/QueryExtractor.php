<?php

/**
 * QueryExtractor - Extrae anotaciones @ai-* de las clases Query
 * 
 * Lee las clases de consultas y extrae las anotaciones para
 * proporcionar ejemplos al asistente IA.
 * 
 * @package NineSys\AI
 */
class QueryExtractor
{
    /**
     * Directorio donde están las clases de queries
     */
    private string $queriesDir;

    /**
     * Cache de queries extraídas
     */
    private array $cache = [];

    public function __construct(string $queriesDir = null)
    {
        $this->queriesDir = $queriesDir ?? __DIR__ . '/../../queries';
    }

    /**
     * Extrae todas las queries de todas las clases
     * 
     * @return array Array con toda la información de queries
     */
    public function extractAll(): array
    {
        if (!empty($this->cache)) {
            return $this->cache;
        }

        $queries = [];
        $files = glob($this->queriesDir . '/*Queries.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');
            if ($className === 'BaseQuery')
                continue;

            require_once $file;

            if (!class_exists($className))
                continue;

            $classQueries = $this->extractFromClass($className);
            if (!empty($classQueries)) {
                $queries[$className] = $classQueries;
            }
        }

        $this->cache = $queries;
        return $queries;
    }

    /**
     * Extrae información de una clase específica
     * 
     * @param string $className Nombre de la clase
     * @return array Array de queries con sus anotaciones
     */
    public function extractFromClass(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $queries = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            $doc = $method->getDocComment();
            if (!$doc || strpos($doc, '@ai-description') === false) {
                continue;
            }

            $queries[] = [
                'metodo' => $method->getName(),
                'descripcion' => $this->extractTag($doc, '@ai-description'),
                'keywords' => $this->extractTag($doc, '@ai-keywords'),
                'ejemplos' => $this->extractTag($doc, '@ai-ejemplo'),
            ];
        }

        return $queries;
    }

    /**
     * Extrae el valor de una anotación
     * 
     * @param string $doc DocComment
     * @param string $tag Nombre del tag (@ai-description, etc)
     * @return string Valor del tag
     */
    private function extractTag(string $doc, string $tag): string
    {
        $pattern = '/' . preg_quote($tag, '/') . '\s+(.+?)(?=\n\s*\*\s*@|\n\s*\*\/)/s';
        if (preg_match($pattern, $doc, $matches)) {
            return trim(preg_replace('/\s*\*\s*/', ' ', $matches[1]));
        }
        return '';
    }

    /**
     * Genera texto de contexto para el prompt de IA
     * 
     * @param array|null $categorias Categorías a incluir (null = todas)
     * @return string Texto formateado para el prompt
     */
    public function generatePromptContext(?array $categorias = null): string
    {
        $all = $this->extractAll();
        $context = "\n\nCONSULTAS SQL DISPONIBLES:\n";
        $context .= "Puedes usar estas consultas como referencia para generar SQL:\n\n";

        foreach ($all as $className => $queries) {
            // Filtrar por categoría si se especificó
            if ($categorias !== null) {
                $categoria = str_replace('Queries', '', $className);
                if (!in_array($categoria, $categorias)) {
                    continue;
                }
            }

            $context .= "=== " . strtoupper(str_replace('Queries', '', $className)) . " ===\n";

            foreach ($queries as $query) {
                $context .= "• {$query['descripcion']}\n";
                $context .= "  Método: {$className}::{$query['metodo']}()\n";
                if ($query['keywords']) {
                    $context .= "  Keywords: {$query['keywords']}\n";
                }
                if ($query['ejemplos']) {
                    $context .= "  Ejemplos de pregunta: {$query['ejemplos']}\n";
                }
                $context .= "\n";
            }
        }

        return $context;
    }

    /**
     * Detecta categorías relevantes basándose en la pregunta del usuario
     * 
     * @param string $query Pregunta del usuario
     * @return array Categorías detectadas
     */
    public function detectCategories(string $query): array
    {
        $query = strtolower($query);
        $categories = [];

        // Mapa de palabras clave a categorías
        $keywordMap = [
            'Ordenes' => ['orden', 'órdenes', 'cliente', 'venta', 'ventas', 'entrega', 'entregar', 'saldo', 'debe', 'deuda', 'producto'],
            'Produccion' => ['producción', 'produccion', 'tarea', 'lote', 'departamento', 'costura', 'corte', 'impresión', 'estampado', 'urgente', 'tiempo', 'horas'],
            'Pagos' => ['pago', 'comision', 'comisión', 'debo', 'deber', 'caja', 'ingreso', 'egreso', 'efectivo', 'transferencia'],
            'Inventario' => ['inventario', 'stock', 'material', 'tela', 'tinta', 'insumo', 'bajo', 'agotado'],
            'Empleados' => ['empleado', 'trabajador', 'personal', 'buscar', 'quién', 'quien', 'nombre'],
        ];

        foreach ($keywordMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    $categories[] = $category;
                    break;
                }
            }
        }

        // Si no se detectó ninguna categoría, incluir las más comunes
        if (empty($categories)) {
            $categories = ['Ordenes', 'Produccion', 'Pagos'];
        }

        return array_unique($categories);
    }
}
