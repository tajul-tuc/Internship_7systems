<style>
.groupSelect,.elementSelect{
	max-width:100%;
}
</style>
<section class="main-content">
	<div class="row">
		<div class="col-md-8">
			<h3>Informationen<br>
               <small>
			   Übersicht über alle Anlagen
			  </small>
			</h3>
		</div>
		<div class="col-md-4" align="right" style="margin-top: 25px">
		</div>
	</div>
    <div class="row">
		<div class="panel panel-default">
			<div class="panel-body">
				<table id="datatable_ainf"   class="table table-striped table-hover">
					<thead class=topred>
						<tr>
                            <th class = "col-md-1">Anlage </th>
                            <th class = "col-md-1">Firma </th>
                            <th class = "col-md-1">PLZ </th>
                            <th class = "col-md-1">Typ </th>
							<th class = "col-md-1">Benutzer </th>
							<th class = "col-md-1">Text </th>
							<th class = "col-md-1">Art </th>
							<th class = "col-md-1">Gruppe </th>
							<th class = "col-md-1">Element </th>
							<th class = "col-md-1">Datum</th>
							<th class = "col-md-1">Aktion</th>
						</tr>
					</thead>
					<tbody>
                    <?
                        require_once("class/model/anlagen_information.php");

                        // 1. query mandant's alarm systems
                        $sql = build_visible_anlagen_sql();
                        $anlagen = $mysqli->query($sql);

                        // 2. iterate over all alarm systems
                        while($a = mysqli_fetch_assoc($anlagen)){
                            $info_model = new model\anlagen_information($a["aid"], $userinfo, $ss);
                            $data = $info_model->createData();
                            
                            // 3. iterate over the information data
                            foreach($data["information"] as $row) {?>
                                <tr data-aid="<?=$a["aid"]?>" data-elementid="<?=$row["id"]?>"  data-mode="information" class="inforow <?=($row["completedate"]>"0")?"success":""; ?>" data-infotype="<?=$row["type"]?>"   data-infoartname="<?=$row["artname"]?>" data-infogroup="<?=$row["group"]?>" data-artid="<?=$row["artid"]?>" data-infoelement="<?=$row["element"]?>" data-creationdate="<?=$row["creationdate"]?>"  >
                                    <td data-sort="<?=$a["nr"]?>"><?=$a["nr"]?></td>
                                    <td data-sort="<?=$a["firma"]?>"><?=$a["firma"]?></td>
                                    <td data-sort="<?=$a["plz"]?>"><?=$a["plz"]?></td>
                                    <td class="infotype" data-sort="<?=$row["type"]?>">
                                        <select name="type" autocomplete="off" class="form-control targetType typeSelect" id="typeSelect">
                                            <option value="Hinweis" <?=($row["type"] == "Hinweis" ? "selected" : "")?> >Hinweis Kunden</option>
                                            <option value="Mangel" <?=($row["type"] == "Mangel" ? "selected" : "")?> >Mangel Kunden</option>
                                            <option value="Notiz" <?=($row["type"] == "Notiz" ? "selected" : "")?> >Notiz Intern</option>
                                        </select>
                                    </td>
                                    <td class="infocreationname" >
                                        <?echo $row["creationname"]?>
                                    </td>
                                    <td class="infocontent" data-sort="<?=$row["content"] ?>">
                                        <textarea name="text" type="text" style="resize: vertical;" class="form-control input-sm content target" data-original="<?$row["content"]?>"><?=$row["content"] ?></textarea>
                                    </td>
                                    <td class="infoartname" data-sort="<?=$row["artname"]?>">
                                        <select name="art" autocomplete="off" class="form-control artSelect targetdb" >
                                            <option disabled value></option>
                                            <? foreach($data["art"] as $art):?>
                                            <option data-artid="<?=$art["id"]?>" value="<?=$art["artname"]?>" <?=($art["artname"] == $row["artname"] ? "selected" : "")?> ><?=$art["artname"]?>
                                            </option>
                                            <?endforeach;?>
                                        </select>
                                    </td>
                                    <td class="infogroup" style="max-width: 150px;" data-sort="<?=$row["group"]?>">
                                        <select name="group" autocomplete="off" class="form-control targetdb groupSelect" data-artid="<?=$row["artid"]?>" >
                                            <? foreach($data["allgemeinegroup"] as $g):?>
                                            <?
                                                if($g["mode"]=="kategorie"){
                                                    $groupid=$g["kat"];
                                                    $name=$g["name"];
                                                }else if($g["mode"]=="netzteil"){
                                                    $groupid=$g["ppnetzid"];
                                                    $name="Netzteil ".$g["text"];
                                                }
                                            ?>
                                                <option data-artid="1"  value="<?=$groupid?>" <?=($name === $row["group"] ? "selected" : "")?> <?=($row["artname"] !== "Allgemein" ? 'style="display:none"' : "")?> ><?=$name?>
                                                </option>
                                            <?endforeach;?>
                                            <? foreach($data["meldergroup"] as $g):?>
                                                <option data-artid="2" value="<?=$g["group"]?>" <?=($g["group"] === $row["group"] ? "selected" : "")?> <?=($row["artname"] !== "Melder" ? 'style="display:none"' : "")?> ><?=$g["group"]?></option>
                                            <?endforeach;?>
                                            <? foreach($data["steuergroup"] as $g):?>
                                                <option data-artid="3"  value="<?=$g["group"]?>" <?=($g["group"] === $row["group"] ? "selected" : "")?> <?=($row["artname"] !== "Steuerung" ? 'style="display:none"' : "")?> ><?=$g["group"]?>
                                                </option>
                                            <?endforeach;?>
                                        </select>
                                    </td >
                                    <td class="infoelement" style="max-width: 150px;" data-sort="<?=$row["element"]?>">
                                        <select name="element" autocomplete="off" class="form-control targetdb elementSelect" id="elementSelect" <?=($row["artname"] === "Steuerung" ? "disabled" : "")?> >
                                            <option disabled value <?=($row["artname"] === "Steuerung" ? "selected" : "")?> ></option>
                                            <? foreach($data["allgemeinepp"] as $e):?>
                                            <?
                                                $content = $e["text"];
                                                if($content==""){
                                                    $content = $e["text1"];
                                                }
                                                
                                                if($e["mode"] == "kategorie"){
                                                    $groupid = $e["kat"];
                                                }else if($e["mode"] == "netzteil"){
                                                    $groupid = $e["ppnetzid"];
                                                }
                                                if(!($e["mode"] == "kategorie") && !($e["mode"] == "netzteil")):?>
                                                    <option data-artid="1"  data-group="<?=$groupid?>" value="<?=$content?>" <?=($content === $row["element"] ? "selected" : "")?> <?=($row["artname"] !== "Allgemein" ? 'style="display:none"' : "")?> ><?=$content?></option>
                                                <?endif;
                                            endforeach;?>
                                            <? foreach($data["melderpp"] as $e):?>
                                                <option data-artid="2" data-group="<?=$e["group"]?>"  value="<?=$e["element"]?>" <?=($e["element"] === $row["element"] ? "selected" : "")?> <?=($row["artname"] !== "Melder" ? 'style="display:none"' : "")?> ><?=$e["element"]?></option>
                                            <?endforeach;?>
							            </select>
                                    </td>
                                    <td class="creationdate" style="max-width: 150px;" data-sort="<?=$row["creationdate"]?>"><?=date('d.m.Y H:i:s', $row[creationdate])?>
                                    </td>
                                    
                                    <td  class="infoaction" style="min-width: 160px;">&nbsp;
                                        <span >
                                            <button type="button" data-operation="update"  class="btn btn-sm btn-default crud_button" title="Speichern" >&nbsp;
                                                <i class="fa fa-save" >
                                                </i>&nbsp;
                                            </button>
                                        </span>
                                    <? if($row["completedate"]=="0"):?>
                                    <span >
                                        <button type="button" data-operation="check" class="btn btn-sm btn-default crud_button" title="Check">&nbsp;
                                            <i class="fa fa-check-square-o" >
                                            </i>&nbsp;
                                        </button>
                                    </span>
                                    <? else: ?>
                                    <span >
                                        <button type="button" data-operation="uncheck"  class="btn btn-sm btn-default crud_button" title="Uncheck">&nbsp;
                                            <i class="fa fa-square-o" >
                                            </i>&nbsp;
                                        </button>
                                    </span>
                                    <?endif; ?>
                                    <span ><button type="button" data-operation="delete"   class="btn btn-sm btn-default crud_button" title="L&ouml;schen" ><img src="images/del.png" width="16px" ></button></span>
                                    
                                    </td>
                                </tr>
                            <?}
                        }
                    ?>
                    </tbody>
				</table>
			</div>
		</div>	
	</div>
</section>
<script type="text/javascript" src="class/view/javascript/anlagen_information.js"/>
