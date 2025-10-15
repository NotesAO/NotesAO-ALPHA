<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csvJson" value="<?php echo htmlentities(json_encode($csvArray));?>">
                <div class="row">
                    <div class="col">
                            <label>Message Template</label><br>
                            <!-- echo nl2br($message_text);-->
                            <textarea class="form-control" id="message_text" name="message_text" rows="8"><?php echo $message_text; ?></textarea>
                    </div>
                    <div class="col">
                        <label>Message Preview</label><br>
                            <?php
                                if(isset($csvData) && count($csvData) > 0){
                                    echo "<textarea class='form-control' id='message_preview' name='message_preview' rows='8' readonly='true'>".str_replace($csvKeysWithDelim, $csvData[0], $message_text)."</textarea>";
                                }
                                else {
                                    echo "<textarea class='form-control' id='message_preview' name='message_preview' rows='8' readonly='true'>".$message_text."</textarea>";
                                }
                            ?>
                    </div>
                </div>
	            <div class="row">
                    <div class="col">
                        <label>Message Recepient CSV File</label>
                        <input class="form-control" type="file" id="recepient_list" name="recepient_list">
                    </div>
                </div>
	            <div class="row">
                    <div class="col">
                        <label>Message Recepient List</label>
                        <?php
                                echo "<table class='table table-bordered table-striped'>";
                                echo "<thead>";
                                $numRows = count($csvKeys);
                                // Headder Row
                                echo "<tr>";
                                foreach($csvKeys as $value) {
                                    echo "<th>$value</th>";
                                }
                                echo "</tr>";
                                echo "</thead>";

                                echo "<tbody>";
                                foreach($csvData as $row) {
                                    echo "<tr>";
                                    foreach($row as $value){
                                        echo "<td>$value</td>";
                                    }
                                    echo "</tr>";
                                }
                                echo "</tbody>";
                                echo "</table>";
                            ?>
                    </div>
                </div>
	            <div class="row">
                    <div class="col-1">
                        <input type="submit" class="btn btn-success" name="action" value="Preview">
                    </div>
                    <div class="col-1">
                        <input type="submit" class="btn btn-danger" name="action" value="Send">
                    </div>
                    <div class="col-1">
                        <a href="index.php" class="btn btn-dark">Cancel</a>
                    </div>
                </div>
            </form>
