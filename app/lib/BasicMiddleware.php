<?php
/*namespace BasicMiddleware;

class Cache extends SlimMiddleware
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function call()
    {
        $key = $this->app->request()->getResourceUri();
        $rsp = $this->app->response();

        $data = $this->fetch($key);
        if ($data) {
            // cache hit... return the cached content
            $rsp["Content-Type"] = $data["content_type"];
            $rsp->body($data["body"]);
            return;
        }

        // cache miss... continue on to generate the page
        $this->next->call();

        if ($rsp->status() == 200) {
            // cache result for future look up
            $this->save($key, $rsp["Content-Type"], $rsp->body());
        }
    }

    protected function fetch($key)
    {
        $query = "SELECT content_type, body FROM cache
            WHERE key = " . $this->db->quote($key);
        $result = $this->db->query($query);
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $result->closeCursor();
        return $row;
    }

    protected function save($key, $contentType, $body)
    {
        $query = sprintf("INSERT INTO cache (key, content_type, body)
            VALUES (%s, %s, %s)",
            $this->db->quote($key),
            $this->db->quote($contentType),
            $this->db->quote($body)
        );
        $this->db->query($query);
    }

}*/