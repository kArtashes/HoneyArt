<form action="search.php" method="GET" id="search-row">
    <button type="submit"><img id="search-form-icon" src="./images/search-form-icon.png" alt=""></button>
    <input id="searchInput" type="text" name="searchValue" placeholder="Search...">
    <input type="hidden" name="searchValue" id="searchValue">
    <button onclick="searchClose()" type="button">x</button>
</form>
<div id="search-overlay">
    <div id="searchResults">
        <!-- products appearing here -->
    </div>
</div>
<script>
    function searchShow(){
        let search = document.getElementById("search-row");
        search.classList.add("show");
        let searchRes =document.getElementById("searchResults");
        searchRes.style.display='block';
        let searchOverlay =document.getElementById("search-overlay");
        searchOverlay.style.display='block';
    }
    function searchClose(){
        let search = document.getElementById("search-row");
        search.classList.remove("show");
        let searchRes =document.getElementById("searchResults");
        searchRes.style.display='none';
        let searchOverlay =document.getElementById("search-overlay");
        searchOverlay.style.display='none';
    }

    const hiddenInput = document.getElementById('searchValue');
    const input =document.getElementById('searchInput');
    input.addEventListener('input', function(){
        hiddenInput.value = this.value; 
        console.log(hiddenInput.value);
    })
    
</script>



<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>


<script type="text/javascript">
    $(document).ready(function(){
        $("#searchInput").keyup(function(){
            var inputText = $(this).val();
            // alert(inputText);

            if(input != ""){
                $.ajax({
                    url:"search.php",
                    method:"POST",
                    data:{inputText:inputText},

                    success:function(data){
                        $("#searchResults").html(data);
                    }
                })
                $("#search-overlay").css("display", "block");
            }
            else{
                $("#searchResults").css("display", "none");
            }
        });
    });
</script>

