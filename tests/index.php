<html lang="en">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    <body>
        <?php
            include_once '../vendor/autoload.php';
            $resultHeaderClass = 'warning';
            $resultHeaderHtml = '';
            $resultBodyHtml = '';
            if ($_FILES['pdf_facturx_extract']) {
                $resultHeaderHtml = 'Extract Factur-X XML from PDF result';
                $facturx = new \Atgp\FacturX\Facturx();
                $resultBodyHtml .= "<h4 class='text-primary'>File ".$_FILES['pdf_facturx_extract']['name'].' : </h4>';
                try {
                    $result = $facturx->getFacturxXmlFromPdf($_FILES['pdf_facturx_extract']['tmp_name'], true);
                } catch (Exception $e) {
                    $resultBodyHtml .= '<pre>Error while retrieving XML Factur-X :'.$e.'</pre>';
                }
                if (!$result) {
                    $resultBodyHtml .= '<div class="alert alert-danger">No valid XML Factur-X found.</div>';
                } else {
                    $resultHeaderClass = 'success';
                    $doc = new DomDocument('1.0');
                    $doc->preserveWhiteSpace = false;
                    $doc->formatOutput = true;
                    $doc->loadXML($result);
                    $resultBodyHtml .= '<pre lang="xml">'.htmlentities($doc->saveXML()).'</pre>';
                }
            }
            if ($_FILES['xml_facturx_check']) {
                $facturx = new \Atgp\FacturX\Facturx();
                $resultHeaderHtml = 'Check XML Factur-X result';
                $resultBodyHtml = "<h4 class='text-primary'>File ".$_FILES['xml_facturx_check']['name'].' : </h4>';
                try {
                    $result = $facturx->checkFacturxXsd($_FILES['xml_facturx_check']['tmp_name']);
                } catch (Exception $e) {
                    $resultBodyHtml .= '<pre>Error while checking the XML :'.$e.'</pre>';
                }
                if ($result === true) {
                    $resultHeaderClass = 'success';
                    $resultBodyHtml .= '<div class="alert alert-success">XML Factur-X valid.</div>';
                } else {
                    $resultBodyHtml .= '<div class="alert alert-warning">XML Factur-X invalid.</div>';
                }
            }
            if ($_FILES['pdf_classic'] && $_FILES['xml_facturx_tolink']) {
                $facturx = new \Atgp\FacturX\Facturx();
                $resultHeaderHtml = 'Generate PDF Factur-X from PDF and Factur-X XML result';
                try {
                    if ($_POST['file_as_string'] == 'true') {
                        $pdf = file_get_contents($_FILES['pdf_classic']['tmp_name']);
                        $facturx_xml = file_get_contents($_FILES['xml_facturx_tolink']['tmp_name']);
                    } else {
                        $pdf = $_FILES['pdf_classic']['tmp_name'];
                        $facturx_xml = $_FILES['xml_facturx_tolink']['tmp_name'];
                    }
                    $attachment_files = array();
                    if (!empty($_FILES['attachment']['tmp_name'])) {
                        $attachment_files[] = array(
                            'name' => $_FILES['attachment']['name'],
                            'desc' => $_POST['attachment_desc'],
                            'path' => $_FILES['attachment']['tmp_name'],
                        );
                    }
                    $result = $facturx->generateFacturxFromFiles($pdf, $facturx_xml,
                        'autodetect', true, __DIR__.'/', $attachment_files, true);
                } catch (Exception $e) {
                    $resultBodyHtml = 'Error while generating the Factur-X :<pre>' . $e.'</pre>';
                }
                if (!empty($result)) {
                    $resultHeaderClass = 'success';
                    $resultBodyHtml = '<div class="alert alert-success">Factur-X PDF file successfully generated.</div>';
                } else {
                    $resultBodyHtml = '<div class="alert alert-warning">Impossible to generate the Factur-X PDF file.</div>'.$resultBodyHtml;
                }
            }
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h2>Factur-X Toolbox</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <form method="post" enctype="multipart/form-data">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        Generate a PDF Factur-X from a PDF and Factur-X XML
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Choose the PDF file</label>
                                            <input type="file" class="form-control-file" name="pdf_classic" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Choose the Factur-X XML file to link</label>
                                            <input type="file" class="form-control-file" name="xml_facturx_tolink" required>
                                        </div>
                                        <div class="form-group">
                                            <label>(Optional) Choose a file to link :</label>
                                            <input type="file" class="form-control-file" name="attachment">
                                        </div>
                                        <div class="form-group">
                                            <label for="attachment_desc">Description of the attachment :</label>
                                            <input type="text" id="attachment_desc" class="form-control" name="attachment_desc">
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <input class="btn btn-primary" type="submit" value="Submit">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="post" enctype="multipart/form-data">
                                <div class="card">
                                    <div class="card-header bg-info text-white">Extract Factur-X XML from a PDF</div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Choose the PDF file containing the Factur-X XML to extract :</label>
                                            <input type="file" class="form-control-file" name="pdf_facturx_extract" required>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <input class="btn btn-primary" type="submit" value="Submit">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="post" enctype="multipart/form-data">
                                <div class="card">
                                    <div class="card-header bg-info text-white">Verify Factur-X XML file</div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Choose the Factur-X XML file to check :</label>
                                            <input type="file" class="form-control-file" name="xml_facturx_check" required>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <input class="btn btn-primary" type="submit" value="Submit">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php if (!empty($resultBodyHtml)){ ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-<?php echo $resultHeaderClass ?> text-white">
                                        <?php echo $resultHeaderHtml ?>
                                    </div>
                                    <div class="card-body">
                                        <?php echo $resultBodyHtml ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br/>
                    <?php } ?>
                </div>
            </div>
        </div>
    </body>
</html>
