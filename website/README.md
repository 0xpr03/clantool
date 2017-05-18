# Website Files

This folder contains files for displaying the data in a website.  
For embedding clantool.php inside your php application the following functions should be called by you:

  - getTitle() return the title
  - getHead() returns the header required
    **bootstrap & jquery is expected to be included**
  - getContent()
    should be called for the body
  - getAjax() is expected to be called when ajaxCont is set
