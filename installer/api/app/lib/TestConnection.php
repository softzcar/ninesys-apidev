<?php
/** Run TestMe Function to get result */
class TestConnection
{
    private $ip_addr;

    public function __construct()
    {
        $this->ip_addr = PING_URL;
    }
    public function myOS()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === (chr(87) . chr(73) . chr(78))) {
            return true;
        }

        return false;
    }
    private function ping($ip_addr)
    {
        if ($this->myOS()) {
            if (!exec("ping -n 1 -w 1 " . $ip_addr . " 2>NUL > NUL && (echo 0) || (echo 1)")) {
                return true;
            }

        } else {
            if (!exec("ping -q -c1 " . $ip_addr . " >/dev/null 2>&1 ; echo $?")) {
                return true;
            }

        }
        return false;
    }
    public function testMe()
    {
        # $ip_addr = "200.58.112.79"; #DNS: www.phpcentral.com
        # $ip_addr = "172.217.2.206"; #ping mia09s02-in-f14.1e100.net  to Google
        # $ip_addr = "nineteengreen.com"; #ping mia09s02-in-f14.1e100.net  to Google
        if ((new TestConnection())->ping($this->ip_addr)) {
            return true;
            // return "El host " . $this->ip_addr . " existe";
        } else {
            return false;
            // return "El host " . $this->ip_addr . " no está activo";
        }

    }
}
