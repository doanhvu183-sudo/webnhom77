<?php /* close content + layout */ ?>
  </div>
</div>
</div>

<script>
(function(){
  const box = document.getElementById("imgPreview");
  const img = document.getElementById("imgPreviewEl");

  function show(url){
    if (!box || !img) return;
    img.src = url;
    box.style.display = "flex";
  }
  function hide(){
    if (!box || !img) return;
    box.style.display = "none";
    img.src = "";
  }

  // hover các ảnh có class js-img
  document.addEventListener("mouseover", function(e){
    const t = e.target;
    if (t && t.classList && t.classList.contains("js-img")) {
      const url = t.getAttribute("data-full");
      if (url) show(url);
    }
  });

  document.addEventListener("mouseout", function(e){
    const t = e.target;
    if (t && t.classList && t.classList.contains("js-img")) hide();
  });
})();
</script>

</body>
</html>
