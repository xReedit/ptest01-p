class httpFecht {    
    
    
    async postJson(url, params) {
        return await this.fetchData(url, {
            method: 'POST',
            body:JSON.stringify(params)
          });
    }


    fetchData = async (url, options = {}) => {
        return await fetch(url, {
            headers: {
                'Content-Type': 'application/json'
              },            
            ...options,
        })
        .then(res => res.json());        
      };

    ajaxPost(url) {
        $.ajax({ type: 'POST', url, data: {p_from:'z'}})
            .done((aa) => {
                console.log( 'res ajax', aa)
            });
    }
}