
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>

    <!-- Modal -->
    <div id="InterkassaModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" id="plans">
                <div class="container">
                    <h1>
                        1.<?php echo $text_select_payment_method; ?><br>
                        2.<?php echo $text_select_currency; ?><br>
                        3.<?php echo $text_press_pay; ?>
                    </h1>
                    <div class="row">

                        <?php foreach ($payment_systems as $ps => $info ) { ?>

                            <div class="col-md-3 text-center payment_system">
                                <div class="panel panel-warning panel-pricing">
                                    <div class="panel-heading">
                                        <img src="/<?php echo $image; ?><?php echo $ps; ?>.png" alt="<?php echo $info['title'] ; ?>">
                                        <h3><?php echo $info['title'] ; ?></h3>
                                    </div>
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div id="radioBtn" class="btn-group">
                                                <?php foreach ($info['currency'] as $currency => $currencyAlias) { ?>
                                                    <?php if ($currency == 'UAH') { ?>
                                                        <a class="btn btn-primary btn-sm active" data-toggle="fun"
                                                           data-title="<?php echo $currencyAlias; ?>"><?php echo $currency; ?></a>
                                                    <?php } else { ?>
                                                        <a class="btn btn-primary btn-sm notActive" data-toggle="fun"
                                                           data-title="<?php echo $currencyAlias; ?>"><?php echo $currency; ?></a>
                                                    <?php } ?>
                                                <?php } ?>
                                            </div>
                                            <input type="hidden" name="fun" id="fun">
                                        </div>
                                    </div>
                                    <div class="panel-footer">
                                        <a class="btn  btn-block btn-success ik-payment-confirmation" data-title="<?php echo $ps ; ?>"
                                           href="#"><?php echo $pay_via ; ?>
                                            <br>
                                            <strong><?php echo $info['title'] ; ?></strong>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>


    <script type="text/javascript">


        $(document).ready(function () {

            $('#apiik').click(function (e) {
               e.preventDefault();
                $('#InterkassaModal').modal('show');

            });

            var curtrigger = false;
            var form =$('#interkassaform');

            $('.ik-payment-confirmation').click(function (e) {
                e.preventDefault();
                if(!curtrigger){
                    alert('Вы не выбрали валюту');
                    return;
                }else{
                    form.submit();
                }
            });

            $('#radioBtn a').click(function () {
                var sel = $(this).data('title');
                var tog = $(this).data('toggle');
                $('#' + tog).prop('value', sel);
                $('a[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('notActive');
                $('a[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('notActive').addClass('active');

                curtrigger = true;
                var ik_cur = this.innerText;
                console.log(ik_cur);
                var ik_pw_via = $(this).attr('data-title');

                if($('input[name =  "ik_pw_via"]').length > 0){
                    $('input[name =  "ik_pw_via"]').val(ik_pw_via);
                }else{
                    form.append(
                        $('<input>', {
                            type: 'hidden',
                            name: 'ik_pw_via',
                            val: ik_pw_via
                        }));
                }

                jQuery.ajax({
                    type:'POST',
                    url: "<?php echo $ajax_url; ?>",
                    data : form.serialize(),
                    success:function(data)
                    {
                        console.log(data);
                        $('input[name =  "ik_sign"]').val(data);
                    }
                });

            });

            $('#radioBtn a').on('click', function () {

            })
        });
    </script>

<style>
    #radioBtn a{
        cursor: pointer;
    }
    #InterkassaModal .input-group,#InterkassaModal h1{
        text-align: center;
    }

    .payment_system h3, .payment_system img {
        display: inline-block;
        width: 100%;
        font-size: 18px;
    }
    .payment_system .panel-heading {
        text-align: center;
    }
    .payment_system .btn-primary {
        background-image: none;
    }
    .payment_system .input-group{
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
    }

    .payment_system .btn-primary {
        padding: 5px 3px;
    }

    .panel-pricing {
        -moz-transition: all .3s ease;
        -o-transition: all .3s ease;
        -webkit-transition: all .3s ease;
    }

    .panel-pricing:hover {
        box-shadow: 0px 0px 30px rgba(0, 0, 0, 0.2);
    }

    .panel-pricing .panel-heading {
        padding: 20px 10px;
    }

    .panel-pricing .panel-heading .fa {
        margin-top: 10px;
        font-size: 58px;
    }

    .panel-pricing .list-group-item {
        color: #777777;
        border-bottom: 1px solid rgba(250, 250, 250, 0.5);
    }

    .panel-pricing .list-group-item:last-child {
        border-bottom-right-radius: 0px;
        border-bottom-left-radius: 0px;
    }

    .panel-pricing .list-group-item:first-child {
        border-top-right-radius: 0px;
        border-top-left-radius: 0px;
    }

    .panel-pricing .panel-body {
        background-color: #f0f0f0;
        font-size: 40px;
        color: #777777;
        padding: 20px;
        margin: 0px;
    }

    #radioBtn .notActive {
        color: #3276b1;
        background-color: #fff;
    }
    div.modal-dialog.modal-lg div#plans.modal-content div.container {
        width: 90% !important;
    }
    div.modal-dialog.modal-lg div#plans.modal-content div.container .row {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }
</style>