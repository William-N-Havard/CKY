<?php
// Implémentation de l'algorithme CKY pour le parsage de PCFG
// William N. Havard, Septembre 2016.


//permet d'afficher toutes les variables lors d'un var_dump (sur 10 niveaux max)
ini_set('xdebug.var_display_max_depth', 10);

// caractère d'ouverture des probas par défaut
$oProba="(";
$fProba=")";

// si le formulaire a bien été validé
if(!empty($_POST['analyser']))
{  
    // tableau dans lequel seront stockées les règles
    $regles=array();
    
    // on vérifie que l'utilisateur a bien posté quelque chose (i. e. qu'une grammaire et qu'une phrase à analyser ont bien été postée + les caractères d'ouverture/fermeture des probas)
    if(!empty($_POST['grammaire']) && !empty($_POST['phrase']) && !empty($_POST['ouvreProb']) && !empty($_POST['fermeProb']))
    {
        // on récupère le contenu du textarea "grammaire"
        $grammaire=explode("\n",trim($_POST['grammaire']));
        // on normalise la phrase en minuscule
        $phrase_a_analyser=trim(strtolower($_POST['phrase']));
        
        // caractère d'ouverture des probas (on leur attribut la valeur voulue par l'utilisateur)
        $oProba=trim($_POST['ouvreProb']);
        $fProba=trim($_POST['fermeProb']);
        
        // traitement de la phrase à analyser (tokenisation sur les espaces). La tokenisation serait rafiner pour prendre en compte les ponctuations sur un systèmes destiné à la production.
        $tokens_phrase=preg_split("/ +/", $phrase_a_analyser);

        // traitement du contenu du textarea
        foreach($grammaire as $traitement_grammaire)
        {
            // on enlève les espaces à droite et à gauche
            $traitement_grammaire=trim($traitement_grammaire);
            // on ne traite pas les lignes vides
            if($traitement_grammaire!="")
            {
                // on récupère l'élément gauche et l'élément droite d'une règle en splitant sur "->"
                $explode_regle=preg_split("/\s*(\-\>)\s*/", $traitement_grammaire);

                $partie_gauche=$explode_regle[0];
                $partie_droite=$explode_regle[1];

                // récupération des probabilités
                preg_match('/(.*?)\s*?('.preg_quote($oProba).'(.*)'.preg_quote($fProba).')\s*$/', $partie_droite, $matches);
                
                // on attribut à la règle la probabilité correspondante
                $regles[$partie_gauche][$matches[1]]=floatval($matches[3]);
                
                // Structure de données du type :
                // 
                //'S' => 
                //    'NP VP' =>  0.9
                //    'VP' =>     0.1
                //'VP' => 
                //    'V NP' =>   0.5
                //    'V' =>      0.1
                //  .
                //  .
                //  .
            }
        }
        
        // on appele la fonction CKY qui va permettre de calculer les probas et donner l'arbre final
        $cky=CKY($tokens_phrase, $regles);
        
        // si la fonction renvoi 0 c'est qu'il y a un problème (i. e. la phrase ne peut pas être analysée)
        if($cky==0)
        {
            $erreur="Cettre phrase ne donne aucun résultat";
        }
    }
    else
    {
        // Message d'erreur si la grammaire ou la phrase sont vides
        $erreur = "La grammaire ou la phrase n'ont pas été indiquées !";
    }
}

// cette fonction permet de calculer les probabilités pour chacune des cellules
function CKY($mots, $grammaire)
{
    // tableau dans lequel seront stockés les scores
    // les commentaires indique également le pseudocode sur lequel je me suis basé (cf. PowerPoint Luca Dini)

    //score = new double[#(words)+1][#(words)+1][#(nonterms)]
    $score=array();
    
    //for i=0; i<#(words); i++
    // for A in nonterms
    //  if A -> words[i] in grammar
    //    score[i][i+1][A] = P(A -> words[i])
    
    // attribution des règles donnant les éléments terminaux aux tokens
    for($i=0; $i<count($mots); $i++)
    {   
        foreach($grammaire as $A=>$valeur)
        {
            foreach($valeur as $symbole=>$proba)
            {
                if($mots[$i]==$symbole)
                {
                    $score[$i][$i+1][$A."->".$symbole]["score"]=$grammaire[$A][$symbole];
                    $score[$i][$i+1][$A."->".$symbole]["loc"]=$i.",".($i+1);
                }
            }
        }
        
        // gestion des règles unaires
        // 
        //boolean added = true
        //while added 
        //  added = false
        //  for A, B in nonterms
        //    if score[i][i+1][B] > 0 && A->B in grammar
        //      prob = P(A->B)*score[i][i+1][B]
        //      if prob > score[i][i+1][A]
        //        score[i][i+1][A] = prob
        //        back[i][i+1][A] = B
        //        added = true
        //
        $added=true;
        while($added)
        {
            $added=false;
            // on parcourt les non-terminaux une fois (A)
            foreach($grammaire as $partie_gauche_A=>$partie_droite_A)
            {
                // parcourt des celulles du bas des colonnes (premières celulles remplies)
                if(isset($score[$i][$i+1]))
                {
                    // on regarde chacune des règles
                    foreach($score[$i][$i+1] as $regle=>$proba)
                    {
                        // on récupère le signe de gauche
                        $signe_gauche=explode("->", $regle);

                        // on regarde s'il existe une règle du type A->B dans la grammaire en sachant que B doit être une règle de la cellule telle que B->X(X=non terminal)
                        if(isset($grammaire[$partie_gauche_A][$signe_gauche[0]]))
                        {
                            // si c'est le cas on calcul la probabilité de cette règle
                            $prob=$grammaire[$partie_gauche_A][$signe_gauche[0]]*$score[$i][$i+1][$regle]["score"];

                            //s'il n'existe encore aucune règle dans la cellule on l'ajoute tout simplement
                            if(!isset($score[$i][$i+1]))
                            {
                                $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["score"]=$prob;
                                $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["loc"]=$i.",".($i+1);
                                $added=true;
                            }
                            else                                            
                            {
                                // on parcourt la liste des règles du type A->...
                                foreach($score[$i][$i+1] as $regleA=>$probaA)
                                {
                                    // et on récupère son signe C
                                    $signe_gauche_A=explode("->", $regleA);
                                    if($signe_gauche_A[0]==$partie_gauche_A)
                                    {
                                        $trouve_A=true;
                                        // Si la proba > à la règle du type A-> ... alors on efface l'ancienne et on met la meilleur
                                        if($prob>$score[$i][$i+1][$regleA]["score"])
                                        {   
                                            $ajout=true;
                                            $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["score"]=$prob;
                                            $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["loc"]=$i.",".($i+1);
                                            $added=true;
                                        }
                                    }
                                }

                                // S'il n'existe aucune règle du type A->... dans la cellule on l'ajout tout simplement
                                if(!isset($trouve_A) && !isset($ajout))
                                {
                                    $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["score"]=$prob;
                                    $score[$i][$i+1][$partie_gauche_A."->".$signe_gauche[0]]["loc"]=$i.",".($i+1);
                                    $added=true;
                                }

                                if(isset($trouve_A)){unset($trouve_A);}
                                if(isset($ajout)){unset($ajout);}
                            }
                        }
                    }
                }
            }
        }        
    }
    

    //for span = 2 to #(words)
    //  for begin = 0 to #(words)- span
    //    end = begin + span
    //    for split = begin+1 to end-1
    //      for A,B,C in nonterms
    //            prob=score[begin][split][B]*score[split][end][C]*P(A->BC)
    //         if prob > score[begin][end][A]
    //           score[begin]end][A] = prob
    //           back[begin][end][A] = new Triple(split,B,C)
    for($span=2; $span<=count($mots);$span++)
    {
        for($begin=0; $begin<=count($mots)-$span;$begin++)
        {
            $end=$begin+$span;
            for($split=$begin+1; $split<=$end-1; $split++)
            {
                // for A, B, C
                foreach($grammaire as $partie_gauche_A=>$partie_droite_A)
                {
                    foreach($grammaire as $partie_gauche_B=>$partie_droite_B)
                    {
                        foreach($grammaire as $partie_gauche_C=>$partie_droite_C)
                        {
                            if(isset($score[$begin][$split]) && isset($score[$split][$end]))
                            {
                                // permet de récupérer dans la celulle $begin, $spit une règle du type B->... 
                                foreach($score[$begin][$split] as $regleB=>$probaB)
                                {
                                    // et on récupère sont signe B 
                                    $signe_gauche_B=explode("->", $regleB);
                                    
                                    // permet de récupérer dans la celulle $begin, $spit une règle du type C->...
                                    foreach($score[$split][$end] as $regleC=>$probaC)
                                    {
                                        // et on récupère son signe C
                                        $signe_gauche_C=explode("->", $regleC);
                                        
                                        // Si dans la grammaire il y a une règle du type A->B C && si dans la celulle [$begin, $split] on a une règle B-> ... && si dans la celulle [$split, $end] on a une règle C-> ...
                                        if(isset($grammaire[$partie_gauche_A][$partie_gauche_B." ".$partie_gauche_C]) && $signe_gauche_B[0]==$partie_gauche_B &&  $signe_gauche_C[0]==$partie_gauche_C)
                                        {
                                            // prob = score de la règle B->...                   *          score de la règle C->...      *                             P(A->BC)
                                            $prob=$score[$begin][$split][$regleB]["score"]*$score[$split][$end][$regleC]["score"]*$grammaire[$partie_gauche_A][$partie_gauche_B." ".$partie_gauche_C];
                                            
                                            //s'il n'existe encore aucune règle dans la cellule on l'ajoute tout simplement
                                            if(!isset($score[$begin][$end]))
                                            {
                                                $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["score"]=$prob;                               
                                                $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["loc"]=$begin.",".$split." ".$split.",".$end;
                                            }
                                            else                                            
                                            {
                                                // on parcourt la liste des règles du type A->...
                                                foreach($score[$begin][$end] as $regleA=>$probaA)
                                                {
                                                    // et on récupère son signe C
                                                    $signe_gauche_A=explode("->", $regleA);
                                                    if($signe_gauche_A[0]==$partie_gauche_A)
                                                    {
                                                        $trouve_A=true;
                                                        // Si la proba > à la règle du type A-> ... alors on efface l'ancienne et on met la meilleur
                                                        if($prob>$score[$begin][$end][$regleA]["score"])
                                                        {   
                                                            $ajout=true;
                                                            unset($score[$begin][$end][$regleA]["score"]);
                                                            $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["score"]=$prob;                               
                                                            $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["loc"]=$begin.",".$split." ".$split.",".$end;
                                                        }
                                                    }
                                                }
                                                
                                                // S'il n'existe aucune règle du type A->... dans la cellule on l'ajout tout simplement
                                                if(!isset($trouve_A) && !isset($ajout))
                                                {
                                                    $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["score"]=$prob;                               
                                                    $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B." ".$partie_gauche_C]["loc"]=$begin.",".$split." ".$split.",".$end;
                                                }
                                                
                                                if(isset($trouve_A)){unset($trouve_A);}
                                                if(isset($ajout)){unset($ajout);}
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // gestion des règles unaires
                
                //boolean added = true
                //while added
                //  added = false
                //  for A, B in nonterms
                //    prob = P(A->B)*score[begin][end][B];
                //    if prob > score[begin][end][A]
                //      score[begin][end][A] = prob
                //      back[begin][end][A] = B
                //      added = true
                
                //partie quasi identique aux conditions unaires plus haut à l'exception qu'il faut enlever la règle ayant la moins forte proba si les deux règles ont le même élément gauche X->...
                $added=true;
                while($added)
                {
                    $added=false;
                    // parcours des éléments non terminaux 2x
                    foreach($grammaire as $partie_gauche_A=>$partie_droite_A)
                    {
                        foreach($grammaire as $partie_gauche_B=>$partie_droite_B)
                        {
                            if(isset($score[$begin][$end]))
                            {
                                // parcours des règles de la cellule en cours 2x
                                foreach($score[$begin][$end] as $regle=>$proba)
                                {
                                    foreach($score[$begin][$end] as $regleA=>$probaA)
                                    {
                                        $signe_gauche_nouvelle_regle=explode("->", $regle);
                                        $signe_gaucheA=explode("->", $regleA);
                                        
                                        // on regarde s'il existe une règle dans la cellule du type A->X Y ($regleA : $partie_gauche_A==$signe_gaucheA[0] -- $partie_gauche_A permet de connaitre le premier signe Z-> ...)
                                        // on regarde s'il y a une règle telle que A->B (B=non terminal avec A=Z de la ligne prec) dans la grammaire 
                                        // pour réécrire A->B dans la celulle si la proba de cette est supérieure à l'ancienne SACHANT que B doit être l'éléement initial d'une des règles de la cellule $partie_gauche_B==$signe_gauche_nouvelle_regle[0]
                                        // on regarde la proba de (A->B)>(A->X Y)
                                        if(isset($grammaire[$partie_gauche_A][$partie_gauche_B]) && $partie_gauche_B==$signe_gauche_nouvelle_regle[0] && $partie_gauche_A==$signe_gaucheA[0])
                                        {
                                            // on calcul la proba
                                            // prob =                  P(A->B)                  *           score[begin][end][B]
                                            $prob=$grammaire[$partie_gauche_A][$partie_gauche_B]*$score[$begin][$end][$regle]["score"];
                                            // si elle est supérieure on la met à la place de l'ancienne ($regleA)
                                            
                                            // si la celulle $score[$begin][$end] est vide on ajoute directement
                                            if(!isset($score[$begin][$end]))
                                            {
                                                // on enlève l'ancienne règle
                                                unset($score[$begin][$end][$regleA]);
                                                // on rajoute la nouvelle
                                                $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["score"]=$prob;
                                                $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["loc"]=$begin.",".$end;
                                                $added=true;
                                            }
                                            else                                            
                                            {
                                                // on parcourt la liste des règles du type A->...
                                                foreach($score[$begin][$end] as $regleA=>$probaA)
                                                {
                                                    // et on récupère son signe A
                                                    $signe_gauche_A=explode("->", $regleA);
                                                    if($signe_gauche_A[0]==$partie_gauche_A)
                                                    {
                                                        $trouve_A=true;
                                                        // Si la proba > à la règle du type A-> ... alors on efface l'ancienne et on met la meilleur
                                                        if($prob>$score[$begin][$end][$regleA]["score"])
                                                        {   
                                                            $ajout=true;
                                                            // on enlève l'ancienne règle
                                                            unset($score[$begin][$end][$regleA]);
                                                            // on rajoute la nouvelle
                                                            $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["score"]=$prob;
                                                            $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["loc"]=$begin.",".$end;
                                                            $added=true;
                                                        }
                                                    }
                                                }
                                                
                                                // S'il n'existe aucune règle du type A->... dans la cellule on l'ajout tout simplement
                                                if(!isset($trouve_A) && !isset($ajout))
                                                {
                                                    // on enlève l'ancienne règle
                                                    unset($score[$begin][$end][$regleA]);
                                                    // on rajoute la nouvelle
                                                    $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["score"]=$prob;
                                                    $score[$begin][$end][$partie_gauche_A."->".$partie_gauche_B]["loc"]=$begin.",".$end;
                                                    $added=true;
                                                }
                                                
                                                if(isset($trouve_A)){unset($trouve_A);}
                                                if(isset($ajout)){unset($ajout);}
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }   
    }
    
    //on retourne l'arbre et le tableau correspondant à la phrase
    //var_dump($score);
    
    for($i=0;$i<count($mots); $i++)
    {
        if(!isset($score[$i][$i+1]))
        {
            return 0;
        }
    }
    
    if(!isset($score[0][count($mots)]))
    {
        return 0;
    }
    
    $cky["flat"] = flat_tree($score, $grammaire, 0, count($mots));
    $cky["arbre"] = build_tree($cky["flat"]);
    $cky["tableau"] = build_table($score, $mots);

    //return buildTree(score, back)
    return $cky;
}

// Cette fonction permet de construire récursivement l'arbre le plus probable correspondant à la phrase
function flat_tree($score, $grammaire, $debut, $fin, $aff="", $symbole="", $it=1)
{
    // permettra de compter le nombre d'itération pour faire les alinéas de l'arbre
    $it++;
    
    // si aucun symbole n'est indiqué, c'est qu'il s'agit du début de l'arbre
    if($symbole=="")
    {
        foreach($score[$debut][$fin] as $clef=>$r_proba_max)
        {
            if(!isset($max) && !isset($max_key))
            {
                $max=$r_proba_max["score"];
                
                $separation=explode("->", $clef);
                
                // récupération des prochains symbole et dans quelle celulle aller les chercher
                $max_key=$separation[0];
                $suite_sym=explode(" ", $separation[1]);
                $suite_loc=explode(" ", $r_proba_max["loc"]);
            }
            else
            {
                if($r_proba_max["score"]>$max)
                {
                    $max=$r_proba_max["score"];
                    
                    // récupération des prochains symbole et dans quelle celulle aller les chercher
                    $separation=explode("->", $clef);
                    $max_key=$separation[0];
                    $suite_sym=explode(" ", $separation[1]);
                    $suite_loc=explode(" ", $r_proba_max["loc"]);
                }
            }
        }

        //echo $max_key." ".$separation[1]." ".$suite_loc;
    }
    else
    {
       // sinon c'est qu'on recherche un symbole en particulier (en fonction de la règle de la grammaire qui l'a appelé).
        if(isset($score[$debut][$fin]))
        {
            // recherche de la probabilité la plus grande
            foreach($score[$debut][$fin] as $clef=>$r_proba_max)
            {
                $separation=explode("->", $clef);
                if($separation[0]==$symbole)
                {
                    if(!isset($max) && !isset($max_key))
                    {
                        $max=$r_proba_max["score"];
                        
                        // récupération des prochains symbole et dans quelle celulle aller les chercher
                        $separation=explode("->", $clef);
                        $max_key=$separation[0];
                        $suite_sym=explode(" ", $separation[1]);
                        $suite_loc=explode(" ", $r_proba_max["loc"]);
                    }
                    else
                    {
                        if($r_proba_max["score"]>$max)
                        {
                            $max=$r_proba_max["score"];
                            
                            // récupération des prochains symbole et dans quelle celulle aller les chercher
                            $separation=explode("->", $clef);
                            $max_key=$separation[0];
                            $suite_sym=explode(" ", $separation[1]);
                            $suite_loc=explode(" ", $r_proba_max["loc"]);
                        }
                    }
                }
            }
        }
    }

    // si $max_key n'existe pas on est sur une feuille de l'arbre
    if(!isset($max_key))
    {
        return $symbole;
        //return "<br>".str_repeat("&nbsp;",$it*7)."|-> <strong>".$symbole."</strong>";
    }
    else
    {
        // on regarde le sous arbre produit par chaque élément 
        for($k=0;$k<count($suite_sym);$k++)
        {
            $suite_coordonnees=explode(",", $suite_loc[$k]);
            //$aff=$aff.build_tree($score, $grammaire, $suite_coordonnees[0], $suite_coordonnees[1], "", $suite_sym[$k], $it);
            if(!isset($grammaire[$suite_sym[$k]]))
            {
                $aff=$aff.flat_tree($score, $grammaire, $suite_coordonnees[0], $suite_coordonnees[1], "", $suite_sym[$k], $it);
            }
            else
            {   
                $aff=$aff.flat_tree($score, $grammaire, $suite_coordonnees[0], $suite_coordonnees[1], "", $suite_sym[$k], $it).")";
            }            
        }
    }            
    
    return $max_key."(".$aff;
    //return "<br>".str_repeat("&nbsp;",$it*7)."|- - ".$max_key.$aff;
}

function build_tree($flat_tree)
{    
    $noeuds=array();
    $signe_branche="|";
    $signe_noeud=$signe_branche."____";
    $ouverture_feuille="(";
    $fermeture_feuille=")";
    
    if(strtolower(substr($flat_tree, 0, 3))!="ROOT")
    {
        $flat_tree="ROOT(".$flat_tree.")";
    }
    // variables utilisées pour la boucle suivante
    $i=1;
    // permet de compter root (root/S) comme le premier noeud
    $nb_par=1;
    $derniere_pos=0;
    // on commence par regarder la position de tous les noeuds (et on les stockes dans le tableau noeuds)
    while($derniere_pos!=strlen($flat_tree))
    {
        // on regarde où se situent les parenthèses ouvrantes et fermante
        $pos_parenthese_ouvrante=strpos($flat_tree, "(", $derniere_pos);
        $pos_parenthese_fermante=strpos($flat_tree, ")", $derniere_pos);
        
        if($pos_parenthese_fermante>$pos_parenthese_ouvrante && $pos_parenthese_ouvrante!=false)
        {   
            // on enregistre la position du noeud
            $noeuds[$i]=$nb_par;
            substr($flat_tree, $derniere_pos-1, ($pos_parenthese_ouvrante+1-$derniere_pos));
            $derniere_pos=$pos_parenthese_ouvrante+1;
            // on incrémente le nombre de parenthèses ouvertes
            $nb_par++;
            
        }
        else
        {
            // on arrive sur une feuille
            substr($flat_tree, $derniere_pos-1,($pos_parenthese_fermante+1-$derniere_pos));
            $derniere_pos=$pos_parenthese_fermante+1;
            // on décrémente le nombre de parenthèses ouvertes
            $nb_par--;
        } 
        $i++;
    }
    
    // variables utilisées pour la boucle suivante
    $nb_par=1;
    $i=1;
    $derniere_pos=0;
    $noeuds_meme_type=array();
    // cette boucle permet de trouver les intervalles d'un noeud de même type à l'autre pour pouvoir les relier par des | 
    while($derniere_pos!=strlen($flat_tree))
    {
        $pos_parenthese_ouvrante=strpos($flat_tree, "(", $derniere_pos);
        $pos_parenthese_fermante=strpos($flat_tree, ")", $derniere_pos);
        
        if($pos_parenthese_fermante>$pos_parenthese_ouvrante && $pos_parenthese_ouvrante!=false)
        {                     
            $plus_haut=false;
            // pour pouvoir relier des noeuds de même type, il faut que que l'intervale entre celui et le suivant ne comporte pas de noeud d'un niveau inférieur, sinon ce n'est pas la même branche
            for($w=$i+1; $w<=max(array_keys($noeuds));$w++)
            {
                if(array_key_exists($w, $noeuds))
                {
                    if($w==max(array_keys($noeuds)))
                    {
                        if($noeuds[$w]!=$nb_par)
                        {
                            $plus_haut=false;
                            break;
                        }
                    }
                    if($noeuds[$w]<$nb_par)
                    {
                        $plus_haut=false;
                        break;
                    }
                    elseif($noeuds[$w]==$nb_par)
                    {
                        $plus_haut=$w;
                        break;
                    }
                }
            }
            
            // si c'est le cas, on enregistre la position du noeud de départ et d'arriver ainsi que leur niveau dans l'arbre (qui correspond au nombre de parenthèses)
            if($plus_haut!=false)
            {
                foreach($noeuds as $key=>$value)
                {
                    if($key>=$i+1 && $key<=$plus_haut )
                    {
                        $noeuds_meme_type[$i."/".$plus_haut]=$nb_par;
                    }
                }
            }
            
            substr($flat_tree, $derniere_pos-1, ($pos_parenthese_ouvrante+1-$derniere_pos));
            $derniere_pos=$pos_parenthese_ouvrante+1;
            // on incrémente le nombre de parenthèses ouvertes
            $nb_par++;
            
        }
        else
        {
            substr($flat_tree, $derniere_pos-1,($pos_parenthese_fermante+1-$derniere_pos));
            $derniere_pos=$pos_parenthese_fermante+1;
            // on décrémente le nombre de parenthèses ouvertes
            $nb_par--;
 
        } 
        
        $i++;
    }
    
    // variable utilisées pour la boucle suivante
    $nb_par=0;
    $i=1;
    $arbre="";
    // permettra de conaitre le nombre de lignes et de colonne a attribuer au textarea
    $row=0;
    $col=0;
    // on parcourt le flat_tree 
    while($flat_tree!="")
    {
        $pos_parenthese_ouvrante=strpos($flat_tree, "(");
        $pos_parenthese_fermante=strpos($flat_tree, ")");
        
       
        if($pos_parenthese_fermante>$pos_parenthese_ouvrante && $pos_parenthese_ouvrante!=false)
        {   
            $comblage="";
            foreach($noeuds_meme_type as $clef=>$valeur)
            {
                $bornes=explode("/", $clef);
                $borne_b=$bornes[0];
                $borne_h=$bornes[1];
                
                // on regarde si le noeud que l'on observe est compris entre deux noeuds de même type pour rajouter des |
                if($i>$borne_b && $i<$borne_h)
                {
                    // on affiche le | au bon endroit (en tenant compte de la longueur de "| - -"
                    $comblage.=str_repeat(" ",($valeur*5)-strlen($signe_noeud)-strlen($comblage)).$signe_branche;
                }                
            }
            
            // on met à jour la valeur de l'arbre
            $arbre.=$comblage.str_repeat(" ",($nb_par*5)-strlen($comblage)).$signe_noeud.substr($flat_tree, 0, $pos_parenthese_ouvrante)."\n";
            // on met à jour le nombre de lignes
            $row++;
            if(strlen($comblage.str_repeat(" ",($nb_par*5)-strlen($comblage)).$signe_noeud.substr($flat_tree, 0, $pos_parenthese_ouvrante)."\n")>$col)
            {
                // on met à jour le nombre de colonne
                $col=strlen($arbre);
            }
            // on met à jour le reste de la chaine qu'il nous reste à traiter
            $flat_tree=substr($flat_tree, $pos_parenthese_ouvrante+1);
            $nb_par++;
            
        }
        else
        {
            // s'il y a bien quelque chose avant la parenthèse vide
            if(substr($flat_tree, 0, $pos_parenthese_fermante)!='')
            {
                $comblage="";
                 // on regarde si le noeud que l'on observe est compris entre deux noeuds de même type pour rajouter des |
                foreach($noeuds_meme_type as $clef=>$valeur)
                {
                    $bornes=explode("/", $clef);
                    $borne_b=$bornes[0];
                    $borne_h=$bornes[1];

                    if($i>$borne_b && $i<$borne_h)
                    {
                        $comblage.=str_repeat(" ",($valeur*5)-strlen($signe_noeud)-strlen($comblage)).$signe_branche;
                    }
                }

                $arbre.= $comblage.str_repeat(" ",($nb_par*5)-strlen($comblage)).$signe_noeud.$ouverture_feuille.substr($flat_tree, 0, $pos_parenthese_fermante).$fermeture_feuille."\n";
                $arbre.= $comblage."\n";
                if(strlen($comblage.str_repeat(" ",($nb_par*5)-strlen($comblage)).$signe_noeud.substr($flat_tree, 0, $pos_parenthese_fermante)."\n")>$col)
                {
                    // on met à jour le nombre de colonnes
                    $col=strlen($arbre);
                }
                // on met à jour le nombre de ligne
                $row+=2;
            }
            $flat_tree=substr($flat_tree, $pos_parenthese_fermante+1);
            $nb_par--;
 
        }      
        
        $i++;
    }
    
    return ["row"=>$row, "col"=>$col, "tree"=>$arbre];
}


function build_table($score, $mots)
{
    // création du tableau
    $tableau='<table style="border:1px solid black; border-collapse: collapse; margin:auto;"><tbody>';
    for($ligne=0;$ligne<count($mots)+2;$ligne++)
    {
        $tableau.='<tr>';
        for($colonne=0;$colonne<count($mots)+1;$colonne++)
        {
            // affichage de la demie matrice
            if($colonne<$ligne-1 && $colonne>0)
            {
                $tableau.='<td style="border:none; background-color:#d6d6d6;">';   
            }
            else
            {
                $tableau.='<td style="border:1px solid black;">';   
            }
            
            //affichage des spans dans 1e colonne * toutes les lignes
            if($colonne==0 && $ligne>1)
            {   
                $tableau.="<center>".($ligne-2)." - ".($ligne-1)."</center>";
            }
            
            // affichage des spans dans 1e ligne * toutes les colonnes
            if($ligne==1 && $colonne>0)
            {   
                $tableau.="<center>".($colonne-1)."-".$colonne."</center>";
            }
            
            // affichage des tokens
            if($ligne==0 && $colonne>0)
            {   
                $tableau.="<center><strong>".$mots[$colonne-1]."</strong></center>";
            }
            
            if($ligne==0 && $colonne==0)
            {
                $tableau.="<center>".'Tokens'."</center>";
            }
                
            if($ligne==1 && $colonne==0)
            {
                $tableau.="<center>".'Ecart'."</center>";
            }
            
            // remplissage des probabilités dans les cases
            foreach($score as $clef=>$valeur)
            {
                foreach($valeur as $num=>$v)
                {
                    if($clef==$ligne-2 && $num==$colonne)
                    {
                        $tableau.='<div class="center">['.$clef.','.$num.']</div><br>';
                        foreach($v as $term=>$val)
                        {
                            $tableau.= '<strong>'.$term.'</strong> <span class="blue">['.$val['loc'].']</span> <span class="red">('.$val['score'].')</span><br>';   
                        }
                    }
                }
                }
            $tableau.='</td>';
        }
        $tableau.='</tr>';
    }
    $tableau.='<tr>'
                . '<td colspan="'.( count($mots)+1).'">'
                    . '<strong>[0, 0]</strong> : identifiant de la celulle<br>'
                    . '<strong>Gras</strong> : règle de la grammaire<br>'
                    . '<span class="blue">[0,0] ou [0,0 0,0]</span> : localisation de la règle suivante<br>'
                    . '<span class="Red">(0.0000)</span> : probabilité de la règle'
                . '</td>'
            . '</tr>';
    $tableau.='</tbody></table>';
    
    return $tableau;
}
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>CYK</title>
        
        <style>
            .center
            {
                text-align:center;
            }
            
            .red
            {
                color:red;
            }
            
            .blue
            {
                color:blue;
            }
            
            .erreur
            {
                color:white;
                background-color:red;
                border:1px dotted white;
                padding:10px;
                text-align:center;
            }
        </style>
    </head>
    <body>
        <center>
            <form method="post" action="CYK.php">
                <table width="100%" height="50%" border=1>
                    <tr>
                        <th colspan=4 align=center>Implémentation de l'algorithme CYK pour le parsage de PCFG<br>William N. Havard</th>
                    </tr>
                    
                    <tr>
                        <th align=center>Grammaire & phrase à analyser</th>
                        <th align=center>Résultat</th>
                    </tr>
                    
                    <tr>
                        <td>
                            <div class="center">
                                <strong>Paramètres :</strong>
                                <br><br> 
                                Ouverture des probabilités 
                                <input type="text" name="ouvreProb" value="<?php echo htmlspecialchars($oProba, ENT_QUOTES, "UTF-8")?>" style="text-align:right;" size="1">
                                <br> 
                                Fermeture des probabilités 
                                <input type="text" name="fermeProb" value="<?php echo htmlspecialchars($fProba, ENT_QUOTES, "UTF-8")?>" style="text-align:right;" size="1">
                            </div>
                            <br>
                            <div class="center"><strong>Grammaire</strong></div>
                            <textarea style="width:100%; height:500px;" name="grammaire">
                                <?php
                                    if(isset($_POST['grammaire']))
                                    {
                                        echo $_POST['grammaire'];            
                                    }
                                    else
                                    {
                                        echo 
                                        "\nS->NP VP(0.9)".
                                        "\nS->VP(0.1)".
                                        "\nVP->V NP(0.5)".
                                        "\nVP->V(0.1)".
                                        "\nVP->V @VP_V(0.3)".
                                        "\nVP->V PP(0.1)".
                                        "\n@VP_V->NP PP(1.0)".
                                        "\nNP->NP NP(0.1)".
                                        "\nNP->NP PP(0.2)".
                                        "\nNP->N(0.7)".
                                        "\nPP->P NP(1.0)".

                                        "\n\nN->people(0.5)".
                                        "\nN->fish(0.2)".
                                        "\nN->tanks(0.2)".
                                        "\nN->rods(0.1)".
                                        "\nV->people(0.1)".
                                        "\nV->fish(0.6)".
                                        "\nV->tanks(0.3)".
                                        "\nP->with(1.0)".
                                        "\nP->in(1.0)";
                                    }
                                ?>
                            </textarea> 
                            <br><br>
                            <div class="center">
                                <strong>Phrase à analyser</strong>
                                <br><input type="text" name="phrase" value="<?php echo isset($_POST['phrase'])? $_POST['phrase']:'fish people fish tanks';?>">
                            </div>
                        </td>
                        <td rowspan="2">
                            <?php
                            if(isset($cky) && $cky!=0)
                            {
                                if(isset($_POST['tableau']))
                                {
                                    echo $cky["tableau"];
                                }
                                                                
                                if(isset($_POST['flat']))
                                {
                                    echo '<br><br><center>'.$cky["flat"].'</center>';
                                }
                                
                                if(isset($_POST['arbre']))
                                {
                                    echo '<br><br><center><textarea style="resize: none;overflow:hidden;border:none;"rows="'.$cky["arbre"]["row"].'" cols="'.$cky["arbre"]["col"].'" readonly>'.$cky["arbre"]["tree"].'</textarea></center>';
                                }
                            }
                            else
                            {
                                if(isset($erreur))
                                {
                                    echo '<div class="erreur">'.$erreur.'</div>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type=checkbox name="tableau" <?php echo isset($_POST['tableau'])?'CHECKED':'' ;?>>afficher le tableau
                            <br><input type=checkbox name="flat" <?php echo isset($_POST['flat'])?'CHECKED':'' ;?>>afficher l'arbre syntaxique (applani)
                            <br><input type=checkbox name="arbre" <?php echo isset($_POST['arbre'])?'CHECKED':!isset($_POST['analyser'])?'CHECKED':'';?>>afficher l'arbre syntaxique
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <center>
                                <input type=submit style="width:200px; height:100px;background-color:#ffff66;font-weight: bold;font-size:30px;" value="Analyser !" name="analyser">
                            </center>
                        </td>
                    </tr>
                </table>
            </form>
        </center>
    </body>
</html>    