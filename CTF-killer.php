<?php

class host{

	//const	END_SHELL	= "; exec sh";
	const	END_SHELL	= "; sleep 5";
	const	PING		= "ping -c 6 ";
	const	NSLOOKUP	= "nslookup ";
	const	WHOIS		= "whois ";
	const	SCAN		= "nmap -sC -p ";
	const	NMAP		= "nmap -sS -sV -sC -A -T4 -O --osscan-guess -v ";
	const	EOL		= "
";

	// SCRIPT
	private $_pwd;
	private $_script;
	private $_path;

	// HOST
	private $_cible;
	private $_domaine;
	private $_ip;

	// SCAN RAPIDE
	private $_scan	= "";
	private $_url	= "";
	private $_http	= "";
	private $_ftp	= "";
	private $_ssh	= "";
	private $_port	= array(21 => "ftp",
				22 => "ssh",
				80 => "http"
				);

	public function __construct($arg){

		$this->_domaine	= "";
		$this->_script 	= $arg;
		$this->_cible	= $arg;
		$this->_pwd	= getcwd()."/";
		$this->_path 	= substr(__FILE__,0 , strlen(__FILE__) - strlen($arg));

		if ($this->is_ip()){
			$this->_ip = $arg;
			$test = shell_exec("host ".$arg.'| grep "not found:" | wc -l ');
			if ($test != 1){
				$this->_domaine = shell_exec("host ".$arg."|awk '{print $5}'");
			}
		}else{
			$this->_domaine = $arg;
			$this->_ip = shell_exec("nslookup ".$arg.' | grep "Address:" | grep "\." | grep -v "#" | awk '."'{print $2}'");
		}
		if ($this->_domaine != ""){
			$this->_url = $this->make_url($this->_domaine);
		}else{
			$this->_url = $this->make_url($this->_ip);
		}
	}

	private function is_ip(){
		return inet_pton($this->_cible) !== false;
	}

	private function make_url($domaine){
		if (preg_match("#http://#", $domaine) OR preg_match("#https://#", $domaine)){
			return ($domaine);
		}
		return ("http://".$domaine);
	}

	private function cmd($command){
		exec('terminator -e "bash -c \"'.$command.self::END_SHELL.'\""');
	}

	private function launch($fonction, $retour){
		echo $fonction." ";
		$fonction = strtolower($fonction);
		$result = $this->$fonction();
		echo $retour.PHP_EOL;
		return ($result);
	}

	public function ping(){
		return (shell_exec(self::PING.$this->_cible));
	}

	public function nslookup(){
		if ($this->_domaine != ""){
			return (shell_exec(self::NSLOOKUP.$this->_domaine));
		}
	}
	
	public function whois(){
		if ($this->_domaine != ""){
			return (shell_exec(self::WHOIS.$this->_domaine));
		}
	}

	public function scan_rapide(){
		foreach ($this->_port as $port => $service){
			$var = "_".$service;
			$test = shell_exec(self::SCAN.$port." ".$this->_ip);
			$this->$var = shell_exec('echo "'.$test.'" | grep "tcp" | grep "open" | grep "'.$service.'" | wc -l');
		}
	}

	public function dirb(){
		$command = "dirb ".$this->_url." -o ".$this->_pwd."dirb.txt ";
		if ($this->_http != ""){
			$this->cmd($command);
		}
	}

	private function dirb_resum(){
		$directory =	shell_exec("cat ".$this->_pwd."dirb.txt | grep 'directory'| awk '{print $4}'");
		$files	   =	shell_exec("cat ".$this->_pwd."dirb.txt | grep 'CODE:200' | awk '{print $2}'");
		$resum	   =	$directory.PHP_EOL.$files.PHP_EOL;
		return ($resum);
	}

	public function nikto(){
		$command = "nikto -url ".$this->_url." -output ".$this->_pwd."nikto.txt";
		if ($this->_http != ""){
			$this->cmd($command);
		}
	}

	public function nmap(){
		$command = self::NMAP.$this->_ip." -oN ".$this->_pwd."nmap.txt; sleep 10";
		$this->cmd($command);
	}

	private function nmap_version($services){
		$explode = explode(SELF::EOL, $services);
		$return = "";
		foreach ($explode as $service){
			if ($service != "" AND $service != "access-denied"){
				$tmp	 = str_replace("(" , " ", $service);
				$tmp2	 = str_replace(")" , " ", $tmp);
				$version = str_replace("  ", " ", $tmp2);
				$exploit = shell_exec("searchsploit '".$version."'");
				$test	 = shell_exec("searchsploit '".$version."' | wc -l");
				if ($test > 3 AND $test < 50){
					$return .= $exploit;
				}
			}
		}
		return ($return);
	}

	private function nmap_resum(){
		$os	 = shell_exec("cat ".$this->_pwd."nmap.txt | grep 'OS CPE\|OS details'");
		$port	 = shell_exec("cat ".$this->_pwd."nmap.txt | grep '/tcp \|| '");
		$service = shell_exec("cat ".$this->_pwd."nmap.txt | grep '/tcp ' | awk '{print $4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15}'");
		$exploit = $this->nmap_version($service);
		file_put_contents($this->_pwd."exploit.txt",	$exploit);
		file_put_contents($this->_pwd."port.txt",	$port);
		$resum = $os.PHP_EOL.$port.PHP_EOL.$exploit.PHP_EOL;
		return ($resum);
	}

	public function ftp(){
		if ($this->_ftp != ""){
			$this->cmd("wget -r ftp://Anonymous:@$this->_ip -P ftp");
		}
	}

	public function all(){
		$ping		= $this->launch("Ping",		"ok");
		$nslookup	= $this->launch("Nslookup",	"ok");
		$whois		= $this->launch("Whois",	"ok");
		$scan		= $this->launch("Scan_rapide",	"ok");
		$ftp		= $this->launch("FTP",		"en cours");
		$dirb		= $this->launch("Dirb",		"en cours");
		$nikto		= $this->launch("Nikto",	"en cours");
		$nmap		= $this->launch("Nmap",		"en cours");
		$pwd		= $this->_pwd;

		file_put_contents($pwd.$this->_ip, "");
		if ($this->_domaine != ""){
			file_put_contents($pwd.$this->_domaine, "");
		}
		file_put_contents($pwd."ping.txt", $ping);
		if ($nslookup != ""){
			file_put_contents($pwd."nslookup.txt", $nslookup);
		}
		if ($whois != ""){
			file_put_contents($pwd."whois.txt", $whois);
		}

		echo "Attente des résultats ...".PHP_EOL;
		sleep(5);
		$test = 0;
		while ($test < 3){
			$test = shell_exec("cat ".$this->_pwd."nmap.txt | wc -l");
		}
		sleep(5);

		echo "Traitement des résultats ...".PHP_EOL;
		$resum	= $ping.PHP_EOL;
		$resum .= $nslookup.PHP_EOL;
		$resum .= $whois.PHP_EOL;
		$resum .= $this->dirb_resum();
		$resum .= shell_exec("cat ".$this->_pwd."nikto.txt");
		$resum .= $this->nmap_resum();
		file_put_contents($pwd."resum.txt", $resum);
		sleep(1);
		echo "FIN".PHP_EOL;

		$this->cmd("less ".$pwd."resum.txt");
	}
}

if (!isset($argv[1])){
	echo $argv[0]." <NOM_DE_DOMAINE/IP>".PHP_EOL;
	exit;
}

$cible = new host($argv[1]);
$cible->all();

?>
