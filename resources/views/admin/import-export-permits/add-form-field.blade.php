{!! $form->render() !!}
<script>
function showOtherVarieties(selectedValue) {
    var otherVarietiesField = document.getElementsByName('other_varieties')[0].parentNode.parentNode;
    if (selectedValue === 'other') {
        otherVarietiesField.style.display = 'block';
    } else {
        otherVarietiesField.style.display = 'none';
    }
}
</script>