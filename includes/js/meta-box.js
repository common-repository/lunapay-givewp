jQuery( function ( $ ) {
  init_lunapay_meta();
  $(".lunapay_customize_lunapay_donations_field input:radio").on("change", function() {
    init_lunapay_meta();
  });
	
  function init_lunapay_meta(){
    if ("enabled" === $(".lunapay_customize_lunapay_donations_field input:radio:checked").val()){
      $(".lunapay_client_id_field").show();
      $(".lunapay_lunakey_field").show();
      $(".lunapay_luna_signature_key_field").show();
      $(".lunapay_group_id_field").show();
      $(".lunapay_server_end_point_field").show();
    } else {
      $(".lunapay_client_id_field").hide();
      $(".lunapay_lunakey_field").hide();
      $(".lunapay_luna_signature_key_field").hide();
      $(".lunapay_group_id_field").hide();
      $(".lunapay_server_end_point_field").hide();
    }
  }
});