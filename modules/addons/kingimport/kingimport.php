<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

/*
**********************************************

         *** Addon Module Example ***

This example addon module demonstrates all
the functions an addon module can contain.

Please refer to the PDF documentation @
http://wiki.whmcs.com/Addon_Modules
for more information

**********************************************
*/

function kingimport_config() {
    $configarray = array(
    "name" => "Importação de Contas na Kinghost",
    "version" => "0.7",
    "author" => "Abaif",
    "language" => "portuguesebr",
    "fields" => array(
        "username" => array ("FriendlyName" => "Usuário da API", "Type" => "text", "Size" => "25", "Description" => "Seu email de acesso ao painel da revenda"),
        "password" => array ("FriendlyName" => "Senha da API", "Type" => "password", "Size" => "25", "Description" => "Sua senha cadastrada para a API"),
        /*"option3" => array ("FriendlyName" => "Option3", "Type" => "yesno", "Size" => "25", "Description" => "Sample Check Box"),
        "option4" => array ("FriendlyName" => "Option4", "Type" => "textarea", "Size" => "25", "Description" => "Textarea"),
        "option5" => array ("FriendlyName" => "Option5", "Type" => "dropdown", "Options" => "1,2,3,4,5", "Description" => "Sample Dropdown"),*/
    ));
    return $configarray;
}

function kingimport_activate() {

    # Create Custom DB Table
    #$query = "CREATE TABLE `mod_kingimport` (`id` INT( 1 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,`demo` TEXT NOT NULL )";
	#$result = mysql_query($query);

}

function kingimport_deactivate() {

    # Remove Custom DB Table
    #$query = "DROP TABLE `mod_kingimport`";
	#$result = mysql_query($query);

}

function kingimport_upgrade($vars) {

    $version = $vars['version'];

    # Run SQL Updates for V1.0 to V1.1
    /*if ($version < 1.1) {
        $query = "ALTER `mod_kingimport` ADD `demo2` TEXT NOT NULL ";
    	$result = mysql_query($query);
    }

    # Run SQL Updates for V1.1 to V1.2
    if ($version < 1.2) {
        $query = "ALTER `mod_kingimport` ADD `demo3` TEXT NOT NULL ";
    	$result = mysql_query($query);
    }*/

}

function kingimport_output($vars) {

    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $option1 = $vars['option1'];
    $option2 = $vars['option2'];
    $option3 = $vars['option3'];
    $option4 = $vars['option4'];
    $option5 = $vars['option5'];
    $LANG = $vars['_lang'];
	if(isset($_POST['acao'])&&$_POST['acao']=='importar'){
		echo "<p>Processo de Importação, Histórico:</p><ul>";
		
		if(count($_POST['dominios'])==0)echo "<li>Nenhum domínio selecionado para importação, finalizando procedimento agora.</li>";
		else{
			echo "<li>Carregando bibliotecas de importação...</li>";
			require('api/Cliente.php');
			require('api/Dominio.php');
			require('api/Plano.php');
			
			echo "<li>Abrindo conexão com KingHost...</li>";
			$dominio = new Dominio($vars['username'],$vars['password']);
			$cliente = new Cliente($vars['username'],$vars['password']);
			$plano = new Plano($vars['username'],$vars['password']);
			
			echo "<li>Concatenando planos de hospedagem da revenda...</li>";
			$planos = array();
			$infoPlano = $plano->getPlanos();
			foreach($infoPlano['body'] as $i){
				$planos[$i['PlanoNome']] = $i['IdPlano'];
			}
			
			echo "<li>Carregando moedas do WHMCS</li>";
			$results = localAPI('getcurrencies');
			$moeda = false;
			echo "<li>Procurando moeda Real Brasileiro (BRL)...</li>";
			foreach($results['currencies'] as $i){
				if($i['code']=='BRL')$moeda = $i['id'];
			}
			if(!$moeda){
				echo "<li>A moeda BRL (Real Brasileiro) não foi encontrada, então o valor será definido com a primeira moeda encontrada.</li>";
				$moeda = $results['currencies'][0]['id'];
			}
			
			echo "<li>Iniciando processamento de domínios.</li>";
			foreach($_POST['dominios'] as $i){
			
				echo "<li>Recuperando dados do domínio $i</li>";
				$info = $dominio->getDadosDominio($i);
				if($info['status']!='ok')echo "<li>Atenção! ".$info['error']."</li>";
				else{
					$info = $info['body'];
					
					echo "<li>Recuperando dados do cliente proprietário do domínio ".$info['dominio']."</li>";
					$infoCliente = $cliente->getClientes($info['idClienteRevenda']);
					if($infoCliente['status']!='ok')echo "<li>Atenção! ".$infoCliente['error']."</li>";
					else{
						$infoCliente = $infoCliente['body'][$info['idClienteRevenda']];
						
						echo "<li>Descobrindo qual produto no WHMCS se refere o plano ".$info['plano']." usado no domínio ".$info['dominio']."...</li>";
						$query = mysql_query("SELECT id FROM tblproducts WHERE configoption1 = ".$planos[$info['plano']]);
						$pacote = mysql_fetch_row($query);
						$pacote = $pacote[0];
						//Se não tem pacote, cria um...
						if(!$pacote){
							echo "<li>Produto não encontrado no WHMCS referente ao plano ".$info['plano'].", iniciando criação do produto.</li>";
							$infoPlano = $plano->getPlanos($info['planoId']);
							$infoPlano = $infoPlano['body'];
							$postfields = array(
								'type' => 'hostingaccount',
								'gid' => 0,
								'name' => $infoPlano['PlanoNome'],
								'description' => $infoPlano['PlanoObs'],
								'showdomainoptions' => true,
								'welcomeemail' => 0,
								'paytype' => 'recurring',
								'autosetup' => '',
								'module' => 'kinghost',
								'configoption1' => $infoPlano['planoId'],
								'pricing['.$moeda.'][monthly]' => $infoPlano['PlanoValor'],
							);
							$results = localAPI('addproduct',$postfields);
							if($results['success']){
								echo "<li>Plano ".$infoPlano['PlanoNome']." criado no WHMCS.</li>";
								$id = $results['pid'];
							}else{
								echo "<li>Atenção Não foi possível cadastrar o plano ".$infoPlano['PlanoNome']." no WHMCS, a importação terá problemas.</li>";
								$id = -1;
							}
						}
						
						echo "<li>Definindo intervalo de pagamento de acordo com o definido no domínio da revenda.</li>";
						$periodos = array(1=>'monthly',3=>'quarterly',6=>'semiannually',12=>'annually',24=>'biennially',36=>'triennially');
						$periodicidade = $periodos[$info['periodicidade']];
						
						echo "<li>Definindo próxima data de cobrança calculada pela data 'Pago Até' + periodicidade em meses...</li>";
						//Próximo pagamento, próxima cobrança = ultimopagamento+(periodicidade "meses")
						$proximacobranca = date_format(date_create($info['pagoAte']." + ".$info['periodicidade']." months"),'Y-m-d');
						
						echo "<li>Pesquisa dados do cliente no whmcs...</li>";
						$query = mysql_query("SELECT COUNT(*) FROM tblclients WHERE email = '".$infoCliente['clienteEmail']."'");
						$qtd = mysql_fetch_row($query);
						if($qtd[0]==0){
							echo "<li>Cliente não encontrado! Cadastrando o cliente ".$infoCliente['clienteNome']." no WHMCS.</li>";
							$nome = explode(" ",$infoCliente['clienteNome'],2);
							//Chama a api para cadastrá-lo
							$postfields = array(
								'firstname' => $nome[0],
								'lastname' => $nome[1],
								'companyname' => $infoCliente['clienteEmpresa'],
								'email' => $infoCliente['clienteEmail'],
								'address1' => $infoCliente['clienteEndereco'],
								'address2' => $infoCliente['clienteBairro'],
								'city' => $infoCliente['clienteCidade'],
								'state' => $infoCliente['clienteEstado'],
								'postcode' => $infoCliente['clienteCEP'],
								'country' => 'BR',
								'phonenumber' => $infoCliente['clienteFone'],
								'password2' => '',
								'noemail' => true,
								'skipvalidation' => true
							);
							$results = localAPI('addclient',$postfields);
							$id = $results['clientid'];
						
						}else{
							echo "<li>Cliente localizado pois já está cadastrado um cliente com o email referente.</li>";
							$query = mysql_query("SELECT id FROM tblclients WHERE email = '".$infoCliente['clienteEmail']."'");
							$id = mysql_fetch_row($query);
							$id = $id[0];
						}
						
						echo "<li>Gerando Pedido no WHMCS para o domínio ".$indo['dominio']."</li>";
						$postfields = array(
							'clientid' => $id,
							'pid' => $pacote,
							'domain' => $info['dominio'],
							'billingcycle' => $periodicidade,
							'noinvoice' => true,
							'noemail' => true,
							'paymentmethod' => 'banktransfer',
						);
						$results = localAPI('addorder',$postfields);
						if ($results["result"]=="success") {
							echo "<li>Pedido efetuado com sucesso! Aceitando pedido agora...</li>";
							$id = $results["orderid"];
							$results = localAPI('acceptorder',array('orderid'=>$id));
						}else{
							echo "<li>Atenção! Não foi possível gerar o pedido para o domínio ".$info['dominio'].". Erro: ".$results['error']."</li>";
						}
					}
				}
			}
		}
		
		echo "<li>Processo de Importação finalizado, se tiver dúvidas sempre copie este histórico para suporte.</li></ul><p><small><em>Script desenvolvido por Abaif Tecnologia</em></small></p>";
	}elseif(isset($_POST['acao'])&&$_POST['acao']=='listar'){
		$conteudo = <<<CONTEUDO
<p>{$LANG['listarinfo']}</p>
<script type="text/javascript">
jQuery(document).ready(function(){
	$("#checkall").toggle(function () {
        jQuery(".checkall").attr("checked","checked");
    },function () {
		jQuery(".checkall").attr("checked","");
	});
});
</script>
<form action="addonmodules.php?module=kingimport" method="post">
<input type="hidden" name="acao" value="importar" />
<p>
	<a href="javascript:void(0)" onclick="jQuery('').hide();jQuery('').removeAttr('checked');">{$LANG['listarocultar']}</a>
	<input type="submit" value="{$LANG['listarimportar']}" />
</p>
<table cellspacing="1" bgcolor="#cccccc" width="750" align="center">
<tbody>
	<tr bgcolor="#efefef" style="font-weight:bold;text-align:center;">
		<td width="20"><input type="checkbox" id="checkall"></td>
		<td>{$LANG['dominio']}</td>
		<td>{$LANG['plano']}</td>
		<td>{$LANG['criado']}</td>
	</tr>
CONTEUDO;
		$cores = array('FFFFFF','CCFF66','FFFF95','e8e8e8','FF9797');
		
		$sql = "SELECT h.id, h.domain, CONCAT(u.firstname,' ',u.lastname) AS usuario, p.configoption1 AS plano FROM tblhosting h INNER JOIN tblclients u ON h.userid = u.id LEFT JOIN products p ON h.packageid = p.id ORDER BY h.domain ASC";
		require('api/Dominio.php');
		
		$kinghost = new Dominio($vars['username'],$vars['password']);
		$dominios = $kinghost->getDominios();
		foreach($dominios['body'] as $dominio){
			//Pega informações do domínio
			$info = $kinghost->getDadosDominio($dominio['id']);
			$info = $info['body'];
			//Verifica se domínio já está cadastrado na hospedagem
			$query = mysql_query("SELECT COUNT(*) FROM tblhosting WHERE domain = '".$dominio['dominio']."'");
			$qtd = mysql_fetch_row($query);
			$query = mysql_query("SELECT COUNT(*) FROM tblhosting WHERE domain = '".$dominio['dominio']."' AND domainstatus = 'Active'");
			$ativo = mysql_fetch_row($query);
			$query = mysql_query("SELECT COUNT(*) FROM tblhosting WHERE domain = '".$dominio['dominio']."' AND domainstatus = 'Cancelled'");
			$cancelado = mysql_fetch_row($query);
			//Define qual cor será usada
			if($ativo[0]>0)$cor = $cores[0];
			elseif($cancelado[0]>0)$cor = $cores[4];
			else $cor = $cores[2];
			//Adiciona linha ao conteúdo
			$conteudo .= "<tr bgcolor=\"#".$cor."\">";
			if($qtd[0]>0)
				$conteudo .= '<td><input type="checkbox" disabled="disabled"></td>';
			else
				$conteudo .= '<td><input type="checkbox" name="dominios[]" value="'.$dominio['id'].'" class="checkall"></td>';
			$conteudo .= '<td>'.$dominio['dominio'].'</td>'.
			'<td align="center">'.$info['plano'].'</td>'.
			'<td align="center">'.date_format(date_create($info['dataCriado']),'d/m/Y').'</td>'.
			'</tr>';
		}
		$conteudo .= '</tbody></table><p><input type="submit" value="Importar" /></p>'.
		'<input type="hidden" name="servidor" value="'.$_POST['servidor'].'" />';
	}else{
		$conteudo = '<p>'.$LANG['intro'].'</p>
<p>'.$LANG['description'].'</p>
<p>'.$LANG['documentation'].'</p>
<form action="addonmodules.php?module=kingimport" method="post">
<input type="hidden" name="acao" value="listar" />
<p><select name="servidor">';
		
		$query = mysql_query("SELECT id, CONCAT(name,' &lt;',hostname,'&gt') FROM tblservers WHERE active = 1 AND type = 'kinghost' ORDER BY name ASC");
		while($i = mysql_fetch_row($query)){
			$conteudo .= '<option value="'.$i[0].'">'.$i[1].'</option>
';
		}
		
		$conteudo .= '</select>
<input type="submit" value="'.$LANG['getaccountlist'].'" /><p>';
	}
	echo $conteudo;

}

function kingimport_sidebar($vars) {

    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $option1 = $vars['option1'];
    $option2 = $vars['option2'];
    $option3 = $vars['option3'];
    $option4 = $vars['option4'];
    $option5 = $vars['option5'];
    $LANG = $vars['_lang'];

    $sidebar = '<span class="header"><img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" /> KingImport</span>
<ul class="menu">
        <li><a href="#">Importação de Contas para Kinghost</a></li>
        <li><a href="#">Versão: '.$version.'</a></li>
    </ul>';
    return $sidebar;

}

?>