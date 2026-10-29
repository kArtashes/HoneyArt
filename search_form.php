<form action="search.php" method="GET" id="search-row">
    <button type="submit"><img id="search-form-icon" src="./images/search-form-icon.png" alt=""></button>
    <input id="searchInput" type="text" name="searchValue" placeholder="Search...">
    <button onclick="searchClose()" type="button">x</button>
</form>
<div id="search-overlay">
    <div id="searchResults">
        <!-- products appearing here -->
    </div>
</div>
<script>
    function searchShow(){
        document.getElementById("search-row").classList.add("show");
        document.getElementById("search-overlay").classList.add("show");
        document.getElementById("searchInput").focus();
    }
    function searchClose(){
        document.getElementById("search-row").classList.remove("show");
        document.getElementById("search-overlay").classList.remove("show");
    }
</script>



<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>


<script type="text/javascript">
    $(document).ready(function(){
        $("#searchInput").keyup(function(){
            var inputText = $(this).val();
            // alert(inputText);

            if(inputText != ""){
                $.ajax({
                    url:"search.php",
                    method:"POST",
                    data:{inputText:inputText},

                    success:function(data){
                        $("#searchResults").html(data);
                    }
                })
                $("#searchResults").show();
            }
            else{
                $("#searchResults").empty().hide();
            }
        });
    });
</script>
