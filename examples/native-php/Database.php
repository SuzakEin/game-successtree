<?php

declare(strict_types=1);

/**
 * Singleton BDD « maison », typique d'un projet PHP natif.
 *
 * SuccessTree n'utilise QUE forceRequest() (et getConnection() pour l'échappement).
 * Ce fichier représente le code de l'hôte : SuccessTree ne le modifie jamais.
 */
final class Database
{
    /** @var Database|null */
    private static $instance = null;

    /** @var array<string, mixed> */
    private static $config = [];

    /** @var mysqli */
    private $mysqli;

    /** @param array<string, mixed> $config host, user, pass, name, port */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(self::$config);
        }
        return self::$instance;
    }

    /** @param array<string, mixed> $c */
    private function __construct(array $c)
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->mysqli = @new mysqli(
            (string)($c['host'] ?? '127.0.0.1'),
            (string)($c['user'] ?? 'root'),
            (string)($c['pass'] ?? ''),
            (string)($c['name'] ?? ''),
            (int)($c['port'] ?? 3306)
        );
        if ($this->mysqli->connect_errno) {
            throw new RuntimeException('Connexion MySQL impossible : ' . $this->mysqli->connect_error);
        }
        // Indispensable pour les accents, les emojis ET pour un échappement correct.
        $this->mysqli->set_charset('utf8mb4');
    }

    private function __clone()
    {
    }

    /**
     * Envoie une requête SQL brute.
     *
     * @return array<int, array<string, mixed>>|bool  lignes associatives pour un SELECT/SHOW,
     *                                               true pour une écriture réussie, false en cas d'erreur.
     */
    public function forceRequest($requete)
    {
        $result = $this->mysqli->query((string)$requete);
        if ($result === false) {
            error_log('[Database] ' . $this->mysqli->error . ' — ' . substr((string)$requete, 0, 300));
            return false;
        }
        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }
        return true;
    }

    /** Connexion brute : permet à SuccessTree d'utiliser real_escape_string(). */
    public function getConnection(): mysqli
    {
        return $this->mysqli;
    }
}
