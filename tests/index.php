<?php
include_once '../vendor/autoload.php';
$resultHeaderClass = 'warning';
$resultHeaderHtml = '';
$resultBodyHtml = '';

if (isset($_FILES['pdf_facturx_extract']) && !empty($_FILES['pdf_facturx_extract'])) {
    $resultHeaderHtml = 'Extract Factur-X XML from PDF result';
    $reader = new \Atgp\FacturX\Reader();
    $resultBodyHtml .= "<h4 class='text-primary'>File ".$_FILES['pdf_facturx_extract']['name'].' : </h4>';
    try {
        $content = file_get_contents($_FILES['pdf_facturx_extract']['tmp_name']);
        $result = $reader->extractXML($content, true);
        $resultHeaderClass = 'success';
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($result);
        $resultBodyHtml .= '<pre lang="xml">'.htmlentities($doc->saveXML()).'</pre>';
    } catch (Exception $e) {
        $resultBodyHtml .= '<div class="alert alert-danger">'.$e.'</div>';
    }
}

if (isset($_FILES['xml_facturx_check']) && !empty($_FILES['xml_facturx_check'])) {
    $validator = new \Atgp\FacturX\XsdValidator();
    $resultHeaderHtml = 'Check XML Factur-X result';
    $resultBodyHtml = "<h4 class='text-primary'>File ".$_FILES['xml_facturx_check']['name'].' : </h4>';
    $content = file_get_contents($_FILES['xml_facturx_check']['tmp_name']);

    try {
        if (!$validator->validate($content)) {
            $resultBodyHtml .= '<div class="alert alert-warning">'.implode('<br />', $validator->getErrors()).'</div>';
        } else {
            $resultHeaderClass = 'success';
            $resultBodyHtml .= '<div class="alert alert-success">XML Factur-X valid.</div>';
        }
    } catch (Exception $e) {
        $resultBodyHtml .= '<pre>Error while checking the XML :'.$e.'</pre>';
    }
}

if (isset($_FILES['pdf_classic']) && !empty($_FILES['pdf_classic'])) {
    $writer = new \Atgp\FacturX\Writer();
    $resultHeaderHtml = 'Generate PDF Factur-X from PDF and Factur-X XML result';
    try {
        $pdf = file_get_contents($_FILES['pdf_classic']['tmp_name']);
        $xml = file_get_contents($_FILES['xml_facturx_tolink']['tmp_name']);
        $attachment_files = [];
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $attachment_files[] = [
                'name' => $_FILES['attachment']['name'],
                'desc' => $_POST['attachment_desc'],
                'path' => $_FILES['attachment']['tmp_name'],
            ];
        }
        $result = $writer->generate($pdf, $xml, null, true, $attachment_files, true, $_POST['relationship']);
    } catch (Exception $e) {
        $resultBodyHtml = 'Error while generating the Factur-X :<pre>'.$e.'</pre>';
    }
    if (!empty($result)) {
        header('Content-disposition: attachment; filename="facturx.pdf"');
        header('Content-Type: application/pdf');
        header('Content-Length: '.strlen($result));
        echo $result;
        exit;
    }
    $resultBodyHtml = '<div class="alert alert-warning">Impossible to generate the Factur-X PDF file.</div>'.$resultBodyHtml;
}
?>
<html lang="en">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    <body>
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
                                            <label>Choose the Factur-X XML file embarkation relationship</label>
                                            <select name="relationship" class="form-control">
                                                <option value="Data" selected>Data</option>
                                                <option value="Source">Source</option>
                                                <option value="Alternative">Alternative</option>
                                            </select>
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
                    <?php if (!empty($resultBodyHtml)) { ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-<?php echo $resultHeaderClass; ?> text-white">
                                        <?php echo $resultHeaderHtml; ?>
                                    </div>
                                    <div class="card-body">
                                        <?php echo $resultBodyHtml; ?>
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