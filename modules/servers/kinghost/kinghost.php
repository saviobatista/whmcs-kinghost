<?php
//Carrega biblioteca principal
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'Kinghost.php');


function kinghost_ConfigOptions($params) {
	//Todo plano na kinghost tem: idPlano,idCliente,PlanoNome,PlanoValor,PlanoPlataforma,PlanoLinguagem,
	//	PlanoEspacoDisco,PlanoEspacoDiscoVirtual,PlanoEspacoEmail,PlanoTrafego,PlanoTrafegoVirtual,PlanoObs,
	//	PlanoPeriodo,PlanoFormaPagamento.
	
	$config = array(
		"Plano" => array( "Type" => "text", "size"=> 8, "Description"=>"ID do Plano, veja o tutorial" ),
		"Limite de Mapeamentos" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Limite de SubDominios" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Limite de MySQL" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Limite de MsSQL" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Limite de PgSQL" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Limite de Firebird" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"FTP Adicional" => array( "Type" => "text", "Size" => "4", "Description" => "(Novos Clientes)" ),
		"Acesso ao UniBox" => array( "Type" => "yesno", "Description" => "(Novos Clientes)" ),
		"Acesso ao FTP" => array( "Type" => "yesno", "Description" => "(Novos Clientes)" ),
		"Download de Backups" => array( "Type" => "yesno", "Description" => "(Novos Clientes)" ),
		//Ainda não implementado pela kinghost: "Troca de Logo do Webmail" => array( "Type" => "yesno", "Description" => "(Novos Clientes)" )
	);

	return $config;

}

function kinghost_CreateAccount($params) {
	require_once('api'.DIRECTORY_SEPARATOR.'Cliente.php');
	
	//Cria o cliente caso não exista ou define para qual cliente será atribuido o produto
	$cliente = null;
	
	$kinghost = new Cliente($params['serverusername'],$params['serverpassword']);
	
	$lista = $kinghost->getClientes();
	if($lista['status']=='ok'){
		//Pega todos os clientes, para validar por email
		foreach($lista['body'] as $id => $i){
			//Se o email cadastrado na kinghost for identico ao email cadastrado no whmcs,
			if($i['clienteEmail']===$params["clientsdetails"]['email']){
				$cliente = $id;//Pega o id dele para cadastrar o domínio
				break;//Interrompe foreach. Parece que isto aqui vai acabar na versão 5.4 do PHP
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	//Se não há cliente cadastrado na revenda com o email informado....
	if($cliente===null){
		//Cadastra o cliente
		$r = $kinghost->addClientes(array(
			'clienteTipo'=>'I',
			'clienteCPFCNPJ'=>null,
			'clienteEmpresa'=>$params["clientsdetails"]["companyname"],
			'clienteNome'=>$params["clientsdetails"]["firstname"].' '.$params["clientsdetails"]["lastname"],
			'clienteSenhaPainel'=>$params["password"],
			'clienteEmail'=>$params["clientsdetails"]["email"],
			'clienteEmailCobranca'=>$params["clientsdetails"]["email"],
			'clienteFone'=>$params["clientsdetails"]["phonenumber"],
			'clienteFax'=>null,
			'clienteCEP'=>$params['clientsdetails']['postcode'],
			'clienteEndereco'=>$params['clientsdetails']['address1'],
			'clienteBairro'=>$params['clientsdetails']['address2'],
			'clienteCidade'=>$params['clientsdetails']['city'],
			'clienteEstado'=>$params['clientsdetails']['state'],
			'clienteLimiteMapeamento'=>$params["configoption2"],
			'clienteLimiteSubdominio'=>$params["configoption3"],
			'clienteLimiteMysql'=>$params["configoption4"],
			'clienteLimiteMssql'=>$params["configoption5"],
			'clienteLimitePgsql'=>$params["configoption6"],
			'clienteLimiteFirebird'=>$params["configoption7"],
			'clienteLimiteFTPADD'=>$params["configoption8"],
			'clienteUniBox'=>$params["configoption9"]=='on'?'on':'off',
			'clienteAcessoFTP'=>$params['configoption10']=='on'?'on':'off',
			'clienteAcessoDownloadBackup'=>$params['configoption11']=='on'?'on':'off'
			//Ainda não implementado pela kinghost: 'cliente '=>$params['configoptions']["Troca de Logo do Webmail"]=='yes'?'on':'off'
		));
		//Confere, se funcionou...
		if($r['status']=='ok'){
			$lista = $kinghost->getClientes();
			if($lista['status']=='ok'){
				//Pega todos os clientes, para validar por email
				foreach($lista['body'] as $id => $i){
					//Se o email cadastrado na kinghost for identico ao email cadastrado no whmcs,
					if($i['clienteEmail']===$params["clientsdetails"]['email']){
						$cliente = $id;//Pega o id dele para cadastrar o domínio
						break;//Interrompe foreach. Parece que isto aqui vai acabar na versão 5.4 do PHP
					}
				}
			}else{
				return $lista['error_msg'];
			}
			//Confere se não há cadastro novamente...
			if($cliente===null){
				//Retorna erro!
				return "Não foi possível localizar o cliente na revenda mesmo depois de cadastrar um novo!";
			}
		//Senão...
		}else{
			//Retorna erro!
			return $r['error_msg'];
		}
	}
	//Só pra garantir, confere se há cliente cadastrado...
	if($cliente===null){
		return "Não foi possível localizar o cliente na revenda mesmo depois de cadastrar um novo!";//Retorna erro!
	}
	
	$kinghost = null;//Limpa variavel
	
	//Adiciona domínio
	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	
	$r = $kinghost->adicionarDominio(array(
		'idCliente'=>$cliente,
		'dominio'=>$params["domain"],
		'senha'=>$params["password"],
		'planoId'=>$params['configoption1'],
		'pagoAte'=>date('Y-m-d')//Pago até hoje pois não há relação direta com o whmcs
	));
	
	if($r['status']=='ok'){//Adicionou com sucesso
		return 'success';
	}else{//Retorna erro
		return $r['error_msg'];
	}

}

function kinghost_TerminateAccount($params) {

	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	
	$id = null;
	$lista = $kinghost->getDominios();
	if($lista['status']=='ok'){
		foreach($lista['body'] as $i){
			if($i['dominio']===$params['domain']){
				$id = $i['id'];
				break;
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	if($id==null)return 'Não foi possível localizar este domínio na sua revenda.';
	else{
		$r = $kinghost->excluirDominio($id);
		if($r['status']=='ok'){
			return 'success';
		}else{
			return $r['error_msg'];
		}
	}

}

function kinghost_SuspendAccount($params) {
	
	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	
	$id = null;
	$lista = $kinghost->getDominios();
	if($lista['status']=='ok'){
		foreach($lista['body'] as $i){
			if($i['dominio']===$params['domain']){
				$id = $i['id'];
				break;
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	if($id==null)return 'Não foi possível localizar este domínio na sua revenda.';
	else{
		$info = $kinghost->getDadosDominio($id);
		if($info['status']=='ok'){
			if($info['body']['ativo']==1){
				$kinghost->doCall( 'dominio/status/'.$id , '' , 'PUT');
				$r = @json_decode($kinghost->getResponseBody() , true);
				if($r['status']=='ok'){
					return 'success';
				}else{
					return $r['error_msg'];
				}
			}else return 'Este domínio já está bloqueado!';
		}else{
			return $info['error_msg'];
		}
	}

}

function kinghost_UnsuspendAccount($params) {
	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	$id = null;
	$lista = $kinghost->getDominios();
	if($lista['status']=='ok'){
		foreach($lista['body'] as $i){
			if($i['dominio']===$params['domain']){
				$id = $i['id'];
			}
		}
	}else{
		return var_dump($lista);
	}
	
	if($id==null)return 'Não foi possível localizar este domínio na sua revenda.';
	else{
		$info = $kinghost->getDadosDominio($id);
		if($info['status']=='ok'){
			if($info['body']['ativo']==0){
				$kinghost->doCall( 'dominio/status/'.$id , '' , 'PUT');
				$resposta = str_replace('Array ( [0] => 1 [1] => registros Google Apps inseridos ) ','',$kinghost->getResponseBody());
				$r = @json_decode($resposta , true);
				if(strlen($resposta)==128||$r['status']=='ok'){
					return 'success';
				}else{
					return print_r($r,true);
				}
			}else return 'Este domínio já está desbloqueado!';
		}else{
			return print_r($info,true);
		}
	}

}

function kinghost_ChangePassword($params) {
	
	require_once('api'.DIRECTORY_SEPARATOR.'Cliente.php');
	
	//Cria o cliente caso não exista ou define para qual cliente será atribuido o produto
	$cliente = null;
	
	$kinghost = new Cliente($params['serverusername'],$params['serverpassword']);
	
	$lista = $kinghost->getClientes();
	if($lista['status']=='ok'){
		//Pega todos os clientes, para validar por email
		foreach($lista['body'] as $id => $i){
			//Se o email cadastrado na kinghost for identico ao email cadastrado no whmcs,
			if($i['clienteEmail']===$params["clientsdetails"]['email']){
				$cliente = $id;//Pega o id dele para cadastrar o domínio
				break;//Interrompe foreach. Parece que isto aqui vai acabar na versão 5.4 do PHP
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	//Cliente existe?
	if($cliente===null){
		return "Não foi possível localizar o cliente na revenda!";//Retorna erro!
	}
	
	//Atualiza senha
	$r = $kinghost->setClientes(array(
		'idCliente'=>$id,
		'clienteSenhaPainel'=>$params['password']
	));
	
	if($lista['status']=='ok')
		return 'success';
	else
		return $r['error_msg'];
	
}

function kinghost_ChangePackage($params) {

	//ID do cliente
	require_once('api'.DIRECTORY_SEPARATOR.'Cliente.php');
	
	//Cria o cliente caso não exista ou define para qual cliente será atribuido o produto
	$cliente = null;
	
	$kinghost = new Cliente($params['serverusername'],$params['serverpassword']);
	
	$lista = $kinghost->getClientes();
	if($lista['status']=='ok'){
		//Pega todos os clientes, para validar por email
		foreach($lista['body'] as $id => $i){
			//Se o email cadastrado na kinghost for identico ao email cadastrado no whmcs,
			if($i['clienteEmail']===$params["clientsdetails"]['email']){
				$cliente = $id;//Pega o id dele para cadastrar o domínio
				break;//Interrompe foreach. Parece que isto aqui vai acabar na versão 5.4 do PHP
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	if($cliente===null){
		return 'Cliente não encontrado em sua revenda!';
	}
	
	$kinghost = null;
	//Dados do domínio
	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	
	$id = null;
	$lista = $kinghost->getDominios();
	if($lista['status']=='ok'){
		foreach($lista['body'] as $i){
			if($i['dominio']===$params['domain']){
				$id = $i['id'];
				break;
			}
		}
	}else{
		return $lista['error_msg'];
	}
	
	if($id==null)return 'Não foi possível localizar este domínio na sua revenda.';
	else{
	
		$r = $kinghost->editaDominio(array(
			'idDominio'=>$id,
			'idCliente'=>$cliente,
			'planoId'=>$params['configoption1']
		));
		
		if($r['status']=='ok')
			return 'success';
		else
			return $r['error_msg'];
	}

}

function kinghost_ClientArea($params) {

	$code = '<form method="post" action="http://painel.'.$params['serverhostname'].'/index_central.php" target="_blank">'.
	'<input type="hidden" name="email" value="'.$params['clientsdetails']['email'].'" />'.
	'<input type="hidden" name="senha" value="'.$params['password'].'" />'.
	'<input type="submit" value="Acessar Painel de Controle" />'.
	'<input type="button" value="Acessar Webmail" onClick="window.open(\'http://webmail.'.$params['serverhostname'].'\')" />'.
	'</form>';
	
	return $code;

}

function kinghost_AdminLink($params) {

	$code = '<form method="post" action="https://painel2.kinghost.net/conectorPainel.php" target="_blank">'.
	'<input type="hidden" name="acao" value="login" />'.
	'<input type="hidden" name="email" value="'.$params["serverusername"].'" />'.
	'<input type="hidden" name="senha" value="'.$params["serverpassword"].'" />'.
	'<input type="submit" value="Acessar Painel de Controle" />'.
	'</form>';
	return $code;

}

function kinghost_LoginLink($params) {
	
	$code = '<form method="post" action="http://painel.'.$params['serverhostname'].'/index_central.php" target="_blank">'.
	'<input type="hidden" name="email" value="'.$params['clientsdetails']['email'].'" />'.
	'<input type="hidden" name="senha" value="'.$params['password'].'" />'.
	'<input type="submit" value="Acessar Painel de Controle" />'.
	'</form>';
	echo $code;
	//echo "<a href=\"http://".$params["serverip"]."/controlpanel?gotousername=".$params["username"]."\" target=\"_blank\" style=\"color:#cc0000\">login to control panel</a>";

}
/*
function kinghost_reboot($params) {

	# Code to perform reboot action goes here...

    if ($successful) {
		$result = "success";
	} else {
		$result = "Error Message Goes Here...";
	}
	return $result;

}

function kinghost_shutdown($params) {

	# Code to perform shutdown action goes here...

    if ($successful) {
		$result = "success";
	} else {
		$result = "Error Message Goes Here...";
	}
	return $result;

}

function kinghost_ClientAreaCustomButtonArray() {
    $buttonarray = array(
	 "Reboot Server" => "reboot",
	);
	return $buttonarray;
}

function kinghost_AdminCustomButtonArray() {
    $buttonarray = array(
	 "Reboot Server" => "reboot",
	 "Shutdown Server" => "shutdown",
	);
	return $buttonarray;
}

function kinghost_extrapage($params) {
    $pagearray = array(
     'templatefile' => 'example',
     'breadcrumb' => ' > <a href="#">Example Page</a>',
     'vars' => array(
        'var1' => 'demo1',
        'var2' => 'demo2',
     ),
    );
	return $pagearray;
}
*/
function kinghost_UsageUpdate($params) {

	$serverid = $params['serverid'];
	$serverhostname = $params['serverhostname'];
	$serverip = $params['serverip'];
	$serverusername = $params['serverusername'];
	$serverpassword = $params['serverpassword'];
	$serveraccesshash = $params['serveraccesshash'];
	$serversecure = $params['serversecure'];

	# Run connection to retrieve usage for all domains/accounts on $serverid
	$results = array();
	require_once('api'.DIRECTORY_SEPARATOR.'Dominio.php');
	
	$kinghost = new Dominio($params['serverusername'],$params['serverpassword']);
	
	$lista = $kinghost->getDominios();
	if($lista['status']=='ok'){
		foreach($lista['body'] as $i){
			$espaco = $kinghost->getEspacoOcupado($i['id']);
			$info = $kinghost->getDadosDominio($i['id']);
			if($espaco['status']=='ok'&&$info['status']=='ok'){
				$results[$i['dominio']] = array(
					"domain"=>$i['dominio'],
					"diskusage"=>floor((float)str_replace('-,','.',$espaco['body']['web'])),
					"disklimit"=>$info['body']['discoWebVirtual'],
					"bwusage"=>floor((float)str_replace('-,','.',$espaco['body']['trafego'])),
					"bwlimit"=>$info['body']['trafego']
				);
			}
		}
	}
	# Now loop through results and update DB

	foreach ($results AS $domain=>$values) {
        update_query("tblhosting",array(
         "diskused"=>$values['diskusage'],
         "dislimit"=>$values['disklimit'],
         "bwused"=>$values['bwusage'],
         "bwlimit"=>$values['bwlimit'],
         "lastupdate"=>"now()",
        ),array("server"=>$serverid,"domain"=>$values['domain']));
    }

}
/*
function kinghost_AdminServicesTabFields($params) {

    $result = select_query("mod_customtable","",array("serviceid"=>$params['serviceid']));
    $data = mysql_fetch_array($result);
    $var1 = $data['var1'];
    $var2 = $data['var2'];
    $var3 = $data['var3'];
    $var4 = $data['var4'];

    $fieldsarray = array(
     'Field 1' => '<input type="text" name="modulefields[0]" size="30" value="'.$var1.'" />',
     'Field 2' => '<select name="modulefields[1]"><option>Val1</option</select>',
     'Field 3' => '<textarea name="modulefields[2]" rows="2" cols="80">'.$var3.'</textarea>',
     'Field 4' => $var4, # Info Output Only
    );
    return $fieldsarray;

}

function kinghost_AdminServicesTabFieldsSave($params) {
    update_query("mod_customtable",array(
        "var1"=>$_POST['modulefields'][0],
        "var2"=>$_POST['modulefields'][1],
        "var3"=>$_POST['modulefields'][2],
    ),array("serviceid"=>$params['serviceid']));
}
*/
?>