<?php
/*
newtextsearch.php
Adaptation de l'action textsearch & newtextsearch de wikini pour Yeswiki
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002  Patrick PAUL
Copyright 2004  Jean Christophe ANDRé
Copyright 2004	Nicephore17
Copyright 2019-2020	XF75013

06/03/2020 - v1.5
	v1		recherche dans le corps du texte
	v1.5	+ recherche dans le nom de page et les tags

INFORMATION D'UTILISATION
Utilisation {{newtextsearch}} en lieu eet place de {{textsearch}}
*/
// On récupére ou initialise toutes le varible comme pour textsearch
// label à afficher devant la zone de saisie
$label = $this->GetParameter('label', _t('WHAT_YOU_SEARCH').'&nbsp;: ');
// largeur de la zone de saisie
$size = $this->GetParameter('size', '40');
// texte du bouton
$button = $this->GetParameter('button', _t('SEARCH'));
// texte à chercher
$phrase = $this->GetParameter('phrase', false);
// séparateur entre les éléments trouvés
$separator = $this->GetParameter('separator', false);

// se souvenir si c'était :
// -- un paramétre de l'action : {{textsearch phrase="Test"}}
// -- ou du CGI http://example.org/wakka.php?wiki=RechercheTexte&phrase=Test
//
// récupérer le paramétre de l'action
$paramPhrase = $phrase;
// ou, le cas échéant, récupérer le paramétre du CGI
if (!$phrase && isset($_GET['phrase'])) $phrase = $_GET['phrase'];

// s'il y a un paramétre d'action "phrase", on affiche uniquement le résultat
// dans le cas contraire, présenter une zone de saisie
if (!$paramPhrase)
{
	echo $this->FormOpen('', '', 'get');
	echo '<div class="input-prepend input-append input-group input-group-lg">
		  <span class="add-on input-group-addon"><i class="glyphicon glyphicon-search icon-search"></i></span>
		  <input name="phrase" type="text" class="form-control" placeholder="'.(($label) ? $label : '').'" size="', $size, '" value="', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '" >
		  <span class="input-group-btn">
		  <input type="submit" class="btn btn-primary btn-lg" value="', $button, '" />
		  </span>
		  </div>
		  <span class="">
		  <small>Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »</small>
		  </span><!-- /input-group --><br>';
	echo "\n", $this->FormClose();
}

/* fonction nécessaire à l'affichage en contexte */
if (!function_exists('DisplaySearchResult')) {
	function DisplaySearchResult($string, $phrase) {
		// encodage des contenus
		$encodag = 'UTF-8';
		// Convertit un texte HTML en texte brut
		$string = preg_replace(",<[^>]*>,U", "", $string);
		// ne pas oublier un < final non ferme
		$string = str_replace('<', ' ', $string);
		$query = rtrim(str_replace("+", " ", $phrase));
		// on recherche toutes les occurences avec les ? et *
		$qt = explode(" ", $query);
		$num = count($qt);
		$cc = round(ceil(154/$num));
		if ($cc < 64 ) $cc = 64;
		for ($i = 0; $i < $num; $i++) {
			$qt[$i] = str_replace(array('*','?'), array('[a-zA-Z0-9]*','[a-zA-Z0-9]?'),$qt[$i]);
			$tab[$i] = preg_split("/($qt[$i])/i", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
			if(count($tab[$i])>1){
				$avant[$i] = mb_substr($tab[$i][0],-$cc,$cc,$encodag);
				$apres[$i] = mb_substr($tab[$i][2],0,$cc,$encodag);
				$string_re .= '<p class="pcherch"><span class="dfsusp">[…]</span>' . $avant[$i] . '<b>' . $tab[$i][1] . '</b>' . $apres[$i] . '<span class="dfsusp">[…]</span></p> ';
			}
		}
		return $string_re;
	}
}

// lancement de la recherche
if ($phrase) {
	// suppression de la limitation de temps d'execution du script
	set_time_limit(0);

	// Modification de caractère sépciaux
	$phrase= str_replace(array('*','?'), array('%','_'),$phrase);
	$phrase = addslashes($phrase);


	// Blablabla SQL
	$requestfull =	'SELECT DISTINCT body, tag FROM '.$prefixe.'yeswiki_pages
					LEFT JOIN '.$prefixe.'yeswiki_acls ON tag = page_tag AND privilege = "read" 
					WHERE latest = "Y"
					AND ( list IS NULL OR list ="*" '.
					($user ? 'OR owner = "'.$user['name'].'" OR list = "+" OR (list NOT LIKE "%!'.$user['name'].'%" AND list LIKE "%'.$user['name'].'")':'').')'.
					('AFFICHER_COMMENTAIRES' ? '':'AND tag NOT LIKE "comment%"').
					(' AND body LIKE "%' . $phrase . '%"').
					' UNION '. 
					'SELECT DISTINCT body, tag FROM '.$prefixe.'yeswiki_pages
					LEFT JOIN '.$prefixe.'yeswiki_acls ON tag = page_tag AND privilege = "read"
					WHERE latest = "Y"
					AND ( list IS NULL OR list ="*" '.
					($user ? 'OR owner = "'.$user['name'].'" OR list = "+" OR (list NOT LIKE "%!'.$user['name'].'%" AND list LIKE "%'.$user['name'].'")':'').')'.
					('AFFICHER_COMMENTAIRES' ? '':'AND tag NOT LIKE "comment%"').
					(' AND tag LIKE "%' . $phrase . '%"').
					' UNION '. 
					'SELECT DISTINCT value, resource FROM '.$prefixe.'yeswiki_triples '.
					(' WHERE value LIKE "%' . $phrase . '%"').
					(' AND resource IS NOT NULL ').
					'ORDER BY tag';

	// exécution de la requête
	if ($resultat = $this->LoadAll($requestfull)) {

		// affichage des resultats
		// restauration de la chaine de base
		$phrase = str_replace(array('%','_'), array('*','?'),$phrase);

		// affichage des résultats en liste
		if (empty($separator)) {
			echo $this->Format('---- --- **Résultats de la recherche « ""'.$phrase.'"" » :---**');
			echo ('<ol class="cherchplus">');
			foreach ($resultat as $i => $page)
			{
				if ($this->HasAccess("read", $page["tag"]))
				{
				$lien = $this->ComposeLinkToPage($page["tag"]);
				$ctllien = $this->IsWikiName($page["tag"]);
				if ($ctllien = "0") {
					$lien = $this->ComposeLinkToPage($page["resource"]);
				}
				echo '<li><h4>', $lien, "</h4>";
				echo DisplaySearchResult($this->Format($page["body"]), $phrase);
				echo "</li>\n";
				}
			}
			echo ('</ol>');

			// affichage des résultats en ligne
		} else {
			foreach ($resultat as $line) { echo ($this->ComposeLinkToPage($line['tag']).' ');};
		};
	} else {
		echo $this->Format('---- --- **Désolé mais il n\'y a aucun résultat pour votre recherche.**');
	};
};
?>
