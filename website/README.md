# Website Files

This folder contains files for displaying the data in a website.  
For embedding clantool2.php inside your php application the following functions should be called by you:

  - getTitle() returns the title
  - getHead() returns the required header  
    **bootstrap3 & jquery is required**
  - getContent()
    body of website
  - getAjax() is expected to be called when POST/GET ajaxCont is set
